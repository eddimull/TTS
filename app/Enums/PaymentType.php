<?php

namespace App\Enums;

enum PaymentType: string
{
    case Cash = 'cash';
    case Check = 'check';
    case Portal = 'portal';
    case Venmo = 'venmo';
    case Zelle = 'zelle';
    case Invoice = 'invoice';
    case Wire = 'wire';
    case CreditCard = 'credit_card';
    case Other = 'other';

    /**
     * Get a human-readable label for the payment type
     */
    public function label(): string
    {
        return match($this) {
            self::Cash => 'Cash',
            self::Check => 'Check',
            self::Portal => 'Client Portal',
            self::Venmo => 'Venmo',
            self::Zelle => 'Zelle',
            self::Invoice => 'Invoice',
            self::Wire => 'Wire Transfer',
            self::CreditCard => 'Credit Card',
            self::Other => 'Other',
        };
    }

    /**
     * Get an icon class for the payment type (PrimeIcons)
     */
    public function icon(): string
    {
        return match($this) {
            self::Cash => 'pi-money-bill',
            self::Check => 'pi-file',
            self::Portal => 'pi-globe',
            self::Venmo => 'pi-mobile',
            self::Zelle => 'pi-mobile',
            self::Invoice => 'pi-file-edit',
            self::Wire => 'pi-building',
            self::CreditCard => 'pi-credit-card',
            self::Other => 'pi-question-circle',
        };
    }

    /**
     * Get a color class for the payment type
     */
    public function color(): string
    {
        return match($this) {
            self::Cash => 'green',
            self::Check => 'blue',
            self::Portal => 'purple',
            self::Venmo => 'cyan',
            self::Zelle => 'indigo',
            self::Invoice => 'orange',
            self::Wire => 'teal',
            self::CreditCard => 'pink',
            self::Other => 'gray',
        };
    }

    /**
     * Get all payment types as an array for dropdowns
     */
    public static function options(): array
    {
        return array_map(
            fn(PaymentType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'icon' => $type->icon(),
                'color' => $type->color(),
            ],
            self::cases()
        );
    }
}
