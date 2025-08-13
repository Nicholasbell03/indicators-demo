<?php

namespace App\Enums;

enum CurrencyEnum: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case ZAR = 'ZAR';
    case JPY = 'JPY';
    case AUD = 'AUD';
    case CAD = 'CAD';
    case CHF = 'CHF';
    case CNY = 'CNY';
    case INR = 'INR';
    case BRL = 'BRL';
    case MXN = 'MXN';
    case SGD = 'SGD';
    case HKD = 'HKD';
    case NOK = 'NOK';
    case SEK = 'SEK';
    case DKK = 'DKK';
    case PLN = 'PLN';
    case CZK = 'CZK';
    case HUF = 'HUF';
    case RUB = 'RUB';
    case TRY = 'TRY';
    case KRW = 'KRW';
    case THB = 'THB';
    case MYR = 'MYR';
    case IDR = 'IDR';
    case PHP = 'PHP';
    case VND = 'VND';
    case EGP = 'EGP';
    case NGN = 'NGN';
    case KES = 'KES';
    case GHS = 'GHS';
    case MAD = 'MAD';
    case TND = 'TND';
    case AED = 'AED';
    case SAR = 'SAR';
    case QAR = 'QAR';
    case KWD = 'KWD';
    case BHD = 'BHD';
    case OMR = 'OMR';
    case JOD = 'JOD';
    case LBP = 'LBP';
    case ILS = 'ILS';

    public function label(): string
    {
        return match ($this) {
            self::USD => 'US Dollar (USD)',
            self::EUR => 'Euro (EUR)',
            self::GBP => 'British Pound (GBP)',
            self::ZAR => 'South African Rand (ZAR)',
            self::JPY => 'Japanese Yen (JPY)',
            self::AUD => 'Australian Dollar (AUD)',
            self::CAD => 'Canadian Dollar (CAD)',
            self::CHF => 'Swiss Franc (CHF)',
            self::CNY => 'Chinese Yuan (CNY)',
            self::INR => 'Indian Rupee (INR)',
            self::BRL => 'Brazilian Real (BRL)',
            self::MXN => 'Mexican Peso (MXN)',
            self::SGD => 'Singapore Dollar (SGD)',
            self::HKD => 'Hong Kong Dollar (HKD)',
            self::NOK => 'Norwegian Krone (NOK)',
            self::SEK => 'Swedish Krona (SEK)',
            self::DKK => 'Danish Krone (DKK)',
            self::PLN => 'Polish Złoty (PLN)',
            self::CZK => 'Czech Koruna (CZK)',
            self::HUF => 'Hungarian Forint (HUF)',
            self::RUB => 'Russian Ruble (RUB)',
            self::TRY => 'Turkish Lira (TRY)',
            self::KRW => 'South Korean Won (KRW)',
            self::THB => 'Thai Baht (THB)',
            self::MYR => 'Malaysian Ringgit (MYR)',
            self::IDR => 'Indonesian Rupiah (IDR)',
            self::PHP => 'Philippine Peso (PHP)',
            self::VND => 'Vietnamese Dong (VND)',
            self::EGP => 'Egyptian Pound (EGP)',
            self::NGN => 'Nigerian Naira (NGN)',
            self::KES => 'Kenyan Shilling (KES)',
            self::GHS => 'Ghanaian Cedi (GHS)',
            self::MAD => 'Moroccan Dirham (MAD)',
            self::TND => 'Tunisian Dinar (TND)',
            self::AED => 'UAE Dirham (AED)',
            self::SAR => 'Saudi Riyal (SAR)',
            self::QAR => 'Qatari Riyal (QAR)',
            self::KWD => 'Kuwaiti Dinar (KWD)',
            self::BHD => 'Bahraini Dinar (BHD)',
            self::OMR => 'Omani Rial (OMR)',
            self::JOD => 'Jordanian Dinar (JOD)',
            self::LBP => 'Lebanese Pound (LBP)',
            self::ILS => 'Israeli Shekel (ILS)',
        };
    }

    public static function selectValues(): array
    {
        $array = [];
        foreach (self::cases() as $case) {
            $array[$case->value] = $case->label();
        }

        return $array;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function symbol(): string
    {
        return match ($this) {
            self::USD => '$',
            self::EUR => '€',
            self::GBP => '£',
            self::ZAR => 'R',
            self::JPY => '¥',
            self::AUD => 'A$',
            self::CAD => 'C$',
            self::CHF => 'CHF',
            self::CNY => '¥',
            self::INR => '₹',
            self::BRL => 'R$',
            self::MXN => '$',
            self::SGD => 'S$',
            self::HKD => 'HK$',
            self::NOK => 'kr',
            self::SEK => 'kr',
            self::DKK => 'kr',
            self::PLN => 'zł',
            self::CZK => 'Kč',
            self::HUF => 'Ft',
            self::RUB => '₽',
            self::TRY => '₺',
            self::KRW => '₩',
            self::THB => '฿',
            self::MYR => 'RM',
            self::IDR => 'Rp',
            self::PHP => '₱',
            self::VND => '₫',
            self::EGP => 'E£',
            self::NGN => '₦',
            self::KES => 'KSh',
            self::GHS => 'GH₵',
            self::MAD => 'MAD',
            self::TND => 'TND',
            self::AED => 'AED',
            self::SAR => 'SAR',
            self::QAR => 'QAR',
            self::KWD => 'KWD',
            self::BHD => 'BHD',
            self::OMR => 'OMR',
            self::JOD => 'JOD',
            self::LBP => 'LBP',
            self::ILS => '₪',
        };
    }
}
