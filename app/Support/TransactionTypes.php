<?php

namespace App\Support;

/**
 * The four transaction types and four statuses of the existing system.
 * Values are fixed by the live schema CHECK constraints and by public/js/data.js —
 * never add, remove or rename them without an approved decision.
 *
 * The UI speaks Arabic type labels (the <select> options have no value
 * attributes, so option text is submitted); the database stores slugs.
 */
//هذا الملف اسمه ItqanData.php، ووظيفته الأساسية تجهيز بيانات النظام من قاعدة البيانات وإرسالها إلى واجهة المستخدم التي تعمل بـ .
 /*
يعني هو حلقة وصل بين Laravel والواجهة الأمامية في مشروعك.
*/

class TransactionTypes
{
    public const SLUGS = ['conversion', 'purchase', 'bills', 'contract'];

    public const STATUSES = ['done', 'pending', 'rejected', 'draft', 'returned'];

    public const LABELS = [
        'conversion' => 'تحويل مبلغ',
        'purchase' => 'شراء أجهزة',
        'bills' => 'دفع فواتير',
        'contract' => 'تعاقد',
    ];


    /**
     * Phase 10 — chart category index per SYSTEM slug (the report bars);
     * admin-defined types fall into the "أخرى" bucket (index 4).
     */
    public const CATEGORY = [
        'conversion' => 0,
        'purchase' => 1,
        'bills' => 2,
        'contract' => 3,
    ];

    /** @var array<string, string>|null slug => name_ar for admin-defined types */
    private static ?array $adminNames = null;

    public static function label(string $slug): string
    {
        if (isset(self::LABELS[$slug])) {
            return self::LABELS[$slug];
        }

        // Phase 4: administrator-defined type — resolve its Arabic name
        // (cached once per request; ItqanData maps every transaction).
        self::$adminNames ??= \App\Models\TrxType::query()
            ->get(['slug', 'name_ar'])
            ->pluck('name_ar', 'slug')
            ->all();

        return self::$adminNames[$slug] ?? $slug;
    }

    /**
     * Accept the slug, the Arabic label or (Phase 4) an admin-defined type's
     * slug/Arabic/English name; null when unknown.
     */
    public static function slug(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (isset(self::LABELS[$value])) {
            return $value;
        }

        $key = array_search($value, self::LABELS, true);

        if ($key !== false) {
            return $key;
        }

        // Admin-defined type (Phase 4). StoreTypeRequest guarantees no name
        // may collide with the four system labels.
        return \App\Models\TrxType::query()
            ->where('slug', $value)
            ->orWhere('name_ar', $value)
            ->orWhere('name_en', $value)
            ->value('slug');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'done' => 'مكتملة',
            'pending' => 'قيد الانتظار',
            'rejected' => 'مرفوضة',
            'draft' => 'مسودة',
            'returned' => 'معادة للاكمال',
            default => $status,
        };
    }
}
