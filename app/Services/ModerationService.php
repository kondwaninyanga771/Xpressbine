<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\Appeal;
use App\Models\ModerationAction;
use App\Models\User;

class ModerationService
{
    public function __construct(
        private PlatformConfig $config,
        private NotificationService $notifications,
    ) {}

    public function assertProviderCanWork(User $providerUser): void
    {
        if (! $providerUser->isProvider()) {
            throw new DomainException('Only a service provider can do that.');
        }

        if ($providerUser->isBanned()) {
            throw new DomainException('This provider is banned.');
        }

        if ($providerUser->isSuspended()) {
            throw new DomainException(
                'This provider is suspended until '.$providerUser->suspended_until->timezone(config('app.timezone'))->toDateTimeString().'.'
            );
        }

        $profile = $providerUser->providerProfile;

        if (! $profile || ! $profile->isVerified()) {
            throw new DomainException('This provider is not verified yet.');
        }
    }

    public function recordNoShow(User $providerUser, ?User $admin = null): void
    {
        $this->recordStrike($providerUser, 'no_show', 'Provider did not show up.', $admin);
    }

    public function recordProviderCancel(User $providerUser): void
    {
        $this->recordStrike($providerUser, 'provider_cancel', 'Provider cancelled a funded booking.', null);
    }

    public function moderate(User $target, User $admin, string $action, string $reason, ?int $days = null): ModerationAction
    {
        if ($target->isAdmin()) {
            throw new DomainException('Admin accounts cannot be moderated this way.');
        }

        return match ($action) {
            'warning' => $this->warn($target, $admin, $reason),
            'suspend' => $this->suspend($target, $admin, $reason, $days ?? (int) $this->config->get('suspension_days_first')),
            'ban' => $this->ban($target, $admin, $reason),
            'reinstate' => $this->reinstate($target, $admin, $reason),
            default => throw new DomainException('Unknown moderation action.'),
        };
    }

    public function appeal(User $provider, ModerationAction $action, string $message): Appeal
    {
        if ($action->user_id !== $provider->id) {
            throw new DomainException('You can only appeal a decision about your own account.');
        }

        if ($action->appeal) {
            throw new DomainException('You have already appealed this decision.');
        }

        $window = (int) $this->config->get('appeal_window_days');

        if ($action->created_at->copy()->addDays($window)->isPast()) {
            throw new DomainException('The appeal window has closed.');
        }

        $appeal = Appeal::query()->create([
            'user_id' => $provider->id,
            'moderation_action_id' => $action->id,
            'message' => $message,
            'status' => 'pending',
        ]);

        return $appeal;
    }

    public function decideAppeal(Appeal $appeal, User $admin, string $decision, ?string $notes): Appeal
    {
        if ($appeal->status !== 'pending') {
            throw new DomainException('This appeal is already decided.');
        }

        if (! in_array($decision, ['accepted', 'rejected'], true)) {
            throw new DomainException('Decision must be accepted or rejected.');
        }

        $appeal->status = $decision;
        $appeal->admin_id = $admin->id;
        $appeal->admin_notes = $notes;
        $appeal->decided_at = now();
        $appeal->save();

        if ($decision === 'accepted') {
            $user = $appeal->user;
            $user->banned_at = null;
            $user->ban_reason = null;
            $user->suspended_until = null;
            $user->save();
        }

        $this->notifications->send(
            $appeal->user,
            'appeal_decided',
            'Appeal '.$decision,
            $notes ?: 'An admin has decided your appeal.',
            ['appeal_id' => $appeal->id],
        );

        return $appeal;
    }

    private function recordStrike(User $providerUser, string $action, string $reason, ?User $admin): void
    {
        $windowDays = (int) $this->config->get('noshow_window_days');
        $recent = ModerationAction::query()
            ->where('user_id', $providerUser->id)
            ->where('action', $action)
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->count();

        $count = $recent + 1;
        $limit = (int) $this->config->get('noshow_limit');

        if ($count >= $limit) {
            $this->ban($providerUser, $admin, $reason.' This is strike '.$count.' within '.$windowDays.' days.');

            return;
        }

        if ($count === 1) {
            $this->suspend(
                $providerUser,
                $admin,
                $reason,
                (int) $this->config->get('suspension_days_first'),
                $action,
            );

            return;
        }

        $this->suspend(
            $providerUser,
            $admin,
            $reason,
            (int) $this->config->get('suspension_days_second'),
            $action,
        );
    }

    private function warn(User $target, User $admin, string $reason): ModerationAction
    {
        $record = $this->store($target, $admin, 'warning', $reason, null);
        $this->notifications->send($target, 'warning', 'Warning', $reason, ['moderation_action_id' => $record->id]);

        return $record;
    }

    private function suspend(User $target, ?User $admin, string $reason, int $days, string $action = 'suspension'): ModerationAction
    {
        $until = now()->addDays($days);
        $target->strikes = $target->strikes + 1;
        $target->suspended_until = $until;
        $target->save();

        $record = $this->store($target, $admin, $action, $reason, $until);

        $this->notifications->send(
            $target,
            'suspended',
            'Account suspended',
            $reason.' You are hidden from search until '.$until->toDateTimeString().'.',
            ['moderation_action_id' => $record->id],
        );

        return $record;
    }

    private function ban(User $target, ?User $admin, string $reason): ModerationAction
    {
        $target->banned_at = now();
        $target->ban_reason = $reason;
        $target->suspended_until = null;
        $target->strikes = max($target->strikes, (int) $this->config->get('max_strikes'));
        $target->save();

        $record = $this->store($target, $admin, 'ban', $reason, null);

        $this->notifications->send(
            $target,
            'banned',
            'Account banned',
            $reason.' You can appeal once within '.$this->config->get('appeal_window_days').' days.',
            ['moderation_action_id' => $record->id],
        );

        return $record;
    }

    private function reinstate(User $target, User $admin, string $reason): ModerationAction
    {
        $target->banned_at = null;
        $target->ban_reason = null;
        $target->suspended_until = null;
        $target->save();

        $record = $this->store($target, $admin, 'reinstate', $reason, null);
        $this->notifications->send($target, 'reinstated', 'Account reinstated', $reason, ['moderation_action_id' => $record->id]);

        return $record;
    }

    private function store(User $target, ?User $admin, string $action, string $reason, ?\Illuminate\Support\Carbon $endsAt): ModerationAction
    {
        return ModerationAction::query()->create([
            'user_id' => $target->id,
            'admin_id' => $admin?->id,
            'action' => $action,
            'reason' => $reason,
            'ends_at' => $endsAt,
        ]);
    }
}
