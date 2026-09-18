<?php

namespace Liern\FilamentTenancy\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ProvisioningStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Provisioning = 'provisioning';
    case Ready = 'ready';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return __('filament-tenancy::tenancy.status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Provisioning => 'warning',
            self::Ready => 'success',
            self::Failed => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedClock,
            self::Provisioning => Heroicon::OutlinedArrowPath,
            self::Ready => Heroicon::OutlinedCheckCircle,
            self::Failed => Heroicon::OutlinedExclamationTriangle,
        };
    }
}
