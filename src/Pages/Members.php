<?php

namespace Liern\FilamentTenancy\Pages;

use Carbon\Carbon;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Liern\FilamentTenancy\Teams\Exceptions\TeamsException;
use Liern\FilamentTenancy\Teams\Invitation;
use Liern\FilamentTenancy\Teams\Invitations;
use Liern\FilamentTenancy\Teams\Teams;

class Members extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $slug = 'members';

    protected string $view = 'filament-tenancy::pages.members';

    public static function getNavigationLabel(): string
    {
        return __('filament-tenancy::teams.members');
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public static function canAccess(): bool
    {
        return config('teams.enabled') && Filament::getTenant() && Filament::auth()->user()
            && app(Teams::class)->membership(Filament::getTenant(), Filament::auth()->user()) !== null;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function team(): Model
    {
        abort_unless(static::canAccess(), 403);

        return Filament::getTenant();
    }

    public function manager(): bool
    {
        return app(Teams::class)->isManagerRole(app(Teams::class)->role($this->team(), Filament::auth()->user()));
    }

    public function owner(): bool
    {
        return app(Teams::class)->role($this->team(), Filament::auth()->user()) === 'owner';
    }

    public function roster(): Collection
    {
        return app(Teams::class)->members($this->team())->get();
    }

    public function invitations(): Collection
    {
        abort_unless($this->manager(), 403);

        return Invitation::where('team_id', (string) $this->team()->getKey())
            ->whereIn('role', array_keys(app(Teams::class)->roles($this->team(), Filament::auth()->user())))
            ->latest()->get();
    }

    public function mayManage(Model $user): bool
    {
        try {
            app(Teams::class)->authorizeManager($this->team(), Filament::auth()->user(), $user);

            return true;
        } catch (TeamsException) {
            return false;
        }
    }

    protected function roleInput(): Select
    {
        return Select::make('role')->label(__('filament-tenancy::teams.role'))->required()
            ->options(fn () => app(Teams::class)->roles($this->team(), Filament::auth()->user()))
            ->default(config('teams.default_role'));
    }

    protected function getHeaderActions(): array
    {
        return [$this->inviteAction(), $this->linkAction(), $this->leaveAction()];
    }

    public function inviteAction(): Action
    {
        return Action::make('invite')->label(__('filament-tenancy::teams.invite'))
            ->visible(fn () => $this->manager())->disabled(fn () => app(Teams::class)->full($this->team()))
            ->schema([Textarea::make('emails')->label(__('filament-tenancy::teams.emails'))->required()->helperText(__('filament-tenancy::teams.emails_help')), $this->roleInput()])
            ->action(function (array $data) {
                $this->run(function () use ($data) {
                    $results = app(Invitations::class)->inviteMany(Filament::auth()->user(), $this->team(), preg_split('/[\s,;]+/', trim($data['emails']), -1, PREG_SPLIT_NO_EMPTY), $data['role']);
                    foreach ($results as $email => $result) {
                        if (is_string($result)) {
                            Notification::make()->title($email)->body($result)->danger()->send();
                        }
                    }
                });
            });
    }

    public function linkAction(): Action
    {
        return Action::make('link')->label(__('filament-tenancy::teams.create_link'))
            ->visible(fn () => $this->manager())->disabled(fn () => app(Teams::class)->full($this->team()))
            ->schema([$this->roleInput(), DateTimePicker::make('expires_at')->label(__('filament-tenancy::teams.expires'))->required()->default(now()->addDays(config('teams.expiry_days')))->after('now'), TextInput::make('use_limit')->label(__('filament-tenancy::teams.use_limit'))->integer()->minValue(1)->maxValue(1000000)->default(1)->required()])
            ->action(fn (array $data) => $this->run(fn () => app(Invitations::class)->create(Filament::auth()->user(), $this->team(), $data['role'], expiresAt: Carbon::parse($data['expires_at']), useLimit: $data['use_limit'])));
    }

    public function roleAction(): Action
    {
        return Action::make('role')->label(__('filament-tenancy::teams.change_role'))->schema([$this->roleInput()])
            ->action(fn (array $arguments, array $data) => $this->run(fn () => app(Teams::class)->changeRole(Filament::auth()->user(), $this->team(), app(Teams::class)->user($arguments['user']), $data['role'])));
    }

    public function removeAction(): Action
    {
        return Action::make('remove')->label(__('filament-tenancy::teams.remove'))->color('danger')->requiresConfirmation()
            ->action(fn (array $arguments) => $this->run(fn () => app(Teams::class)->removeMember(Filament::auth()->user(), $this->team(), app(Teams::class)->user($arguments['user']))));
    }

    public function transferAction(): Action
    {
        return Action::make('transfer')->label(__('filament-tenancy::teams.transfer'))->requiresConfirmation()
            ->action(fn (array $arguments) => $this->run(fn () => app(Teams::class)->transferOwnership(Filament::auth()->user(), $this->team(), app(Teams::class)->user($arguments['user']))));
    }

    public function leaveAction(): Action
    {
        return Action::make('leave')->label(__('filament-tenancy::teams.leave'))->color('danger')->requiresConfirmation()
            ->action(fn () => $this->run(function () {
                app(Teams::class)->removeMember(Filament::auth()->user(), $this->team(), Filament::auth()->user());
                $this->redirect(Filament::getCurrentPanel()->getUrl());
            }));
    }

    protected function invitation(array $arguments): Invitation
    {
        return Invitation::where('team_id', (string) $this->team()->getKey())->findOrFail($arguments['invitation']);
    }

    public function resendAction(): Action
    {
        return Action::make('resend')->label(__('filament-tenancy::teams.resend'))
            ->action(fn (array $arguments) => $this->run(fn () => app(Invitations::class)->resend(Filament::auth()->user(), $this->invitation($arguments))));
    }

    public function revokeAction(): Action
    {
        return Action::make('revoke')->label(__('filament-tenancy::teams.revoke'))->color('danger')->requiresConfirmation()
            ->action(fn (array $arguments) => $this->run(fn () => app(Invitations::class)->revoke(Filament::auth()->user(), $this->invitation($arguments))));
    }

    protected function run(Closure $callback): mixed
    {
        try {
            $result = $callback();
            Notification::make()->title(__('filament-tenancy::teams.saved'))->success()->send();

            return $result;
        } catch (TeamsException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return null;
        }
    }
}
