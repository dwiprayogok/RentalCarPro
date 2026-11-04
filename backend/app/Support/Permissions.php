<?php

namespace App\Support;

use App\Enums\UserRole;

/**
 * Katalog permission granular dan matriks role.
 *
 * Sumber kebenaran tunggal: seeder membaca kelas ini, Policy memakai konstanta ini,
 * dan /api/v1/auth/me mengembalikan nama-nama ini ke frontend.
 */
final class Permissions
{
    // Dashboard
    public const DASHBOARD_VIEW = 'dashboard.view';

    // Sewa
    public const RENTAL_VIEW = 'rental.view';

    public const RENTAL_CREATE = 'rental.create';

    public const RENTAL_UPDATE = 'rental.update';

    public const RENTAL_CANCEL = 'rental.cancel';

    public const RENTAL_DELETE = 'rental.delete';

    public const RENTAL_HANDOVER = 'rental.handover';

    public const RENTAL_RETURN = 'rental.return';

    public const RENTAL_DISCOUNT = 'rental.discount';

    public const RENTAL_WAIVE_FEE = 'rental.waive_fee';

    public const RENTAL_OVERRIDE_BLACKLIST = 'rental.override_blacklist';

    // Pembayaran
    public const PAYMENT_VIEW = 'payment.view';

    public const PAYMENT_CREATE = 'payment.create';

    public const PAYMENT_REFUND = 'payment.refund';

    public const PAYMENT_DELETE = 'payment.delete';

    // Armada
    public const CAR_VIEW = 'car.view';

    public const CAR_CREATE = 'car.create';

    public const CAR_UPDATE = 'car.update';

    public const CAR_DELETE = 'car.delete';

    public const CAR_PUBLISH = 'car.publish';

    // Pelanggan
    public const CUSTOMER_VIEW = 'customer.view';

    public const CUSTOMER_CREATE = 'customer.create';

    public const CUSTOMER_UPDATE = 'customer.update';

    public const CUSTOMER_DELETE = 'customer.delete';

    public const CUSTOMER_CHANGE_RISK = 'customer.change_risk';

    public const CUSTOMER_VIEW_DOCUMENTS = 'customer.view_documents';

    // Driver
    public const DRIVER_VIEW = 'driver.view';

    public const DRIVER_CREATE = 'driver.create';

    public const DRIVER_UPDATE = 'driver.update';

    public const DRIVER_DELETE = 'driver.delete';

    // Perawatan
    public const MAINTENANCE_VIEW = 'maintenance.view';

    public const MAINTENANCE_CREATE = 'maintenance.create';

    public const MAINTENANCE_UPDATE = 'maintenance.update';

    public const MAINTENANCE_DELETE = 'maintenance.delete';

    // Permintaan booking dari landing
    public const BOOKING_REQUEST_VIEW = 'booking_request.view';

    public const BOOKING_REQUEST_UPDATE = 'booking_request.update';

    public const BOOKING_REQUEST_CONVERT = 'booking_request.convert';

    // Laporan
    public const REPORT_OPERATIONAL = 'report.operational';

    public const REPORT_VIEW = 'report.view';

    public const REPORT_EXPORT = 'report.export';

    // Sistem
    public const SETTING_VIEW = 'setting.view';

    public const SETTING_UPDATE = 'setting.update';

    public const LANDING_MANAGE = 'landing.manage';

    public const USER_MANAGE = 'user.manage';

    public const ACTIVITY_VIEW = 'activity.view';

    /** @return list<string> */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }

    /**
     * Matriks role → permission.
     *
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        $all = self::all();

        $admin = array_values(array_diff($all, [
            self::SETTING_UPDATE,
            self::LANDING_MANAGE,
            self::USER_MANAGE,
            self::RENTAL_OVERRIDE_BLACKLIST,
        ]));

        $staff = [
            self::DASHBOARD_VIEW,
            self::RENTAL_VIEW,
            self::RENTAL_CREATE,
            self::RENTAL_UPDATE,
            self::RENTAL_HANDOVER,
            self::RENTAL_RETURN,
            self::PAYMENT_VIEW,
            self::PAYMENT_CREATE,
            self::CAR_VIEW,
            self::CUSTOMER_VIEW,
            self::CUSTOMER_CREATE,
            self::CUSTOMER_UPDATE,
            self::CUSTOMER_VIEW_DOCUMENTS,
            self::DRIVER_VIEW,
            self::MAINTENANCE_VIEW,
            self::BOOKING_REQUEST_VIEW,
            self::BOOKING_REQUEST_UPDATE,
            self::REPORT_OPERATIONAL,
            self::SETTING_VIEW,
        ];

        return [
            UserRole::Owner->value => $all,
            UserRole::Admin->value => $admin,
            UserRole::Staff->value => $staff,
        ];
    }
}
