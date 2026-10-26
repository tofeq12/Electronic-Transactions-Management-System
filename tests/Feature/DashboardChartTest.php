<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * رسم «إحصائيات المعاملات» في الداشبورد: كان زخرفة SVG ثابتة (سلّم 400
 * ويناير–يونيو مكتوبًا يدويًا)، والآن أعمدة ديناميكية فعلية بسلّم محسوب.
 */
class DashboardChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_chart_renders_real_monthly_counts(): void
    {
        $admin = User::factory()->admin()->create();

        // 4 معاملات هذا الشهر + 1 في الشهر الماضي (تاريخ المعاملة هو المُجمِّع).
        Transaction::factory()->count(4)->create([
            'created_by' => $admin->id,
            'dept_id' => null,
            'date' => now()->format('Y-m-d'),
        ]);
        Transaction::factory()->create([
            'created_by' => $admin->id,
            'dept_id' => null,
            'date' => now()->subMonths(1)->startOfMonth()->format('Y-m-d'),
        ]);

        $html = $this->actingAs($admin)->get('/dashboard')->assertOk()->getContent();

        // خط دائري ناعم بقيم فعلية ونقاط (SVG/CSS فقط — بلا مكتبات).
        $this->assertStringContainsString('class="chart-line"', $html);
        $this->assertStringContainsString('pathLength="1"', $html);
        $this->assertStringContainsString('class="chart-dot"', $html);
        $this->assertStringContainsString('text-anchor="middle">4</text>', $html);
        $this->assertStringContainsString('text-anchor="middle">1</text>', $html);

        // السلّم لم يعد مكتوبًا يدويًا (كان 400 ثابتًا بلا بيانات).
        $this->assertStringNotContainsString('text-anchor="end">400</text>', $html);
    }

    public function test_dashboard_chart_scales_when_no_transactions(): void
    {
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->get('/dashboard')->assertOk()->getContent();

        // بلا معاملات: الرسم يبقى صالحًا (الخط عند الصفر) بلا نقاط ولا أعمدة صفرية.
        $this->assertStringContainsString('class="chart-line"', $html);
        $this->assertStringNotContainsString('class="chart-dot"', $html);
    }
}
