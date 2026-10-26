<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\TrxType;
use App\Models\TrxWorkflow;
use App\Models\TrxWorkflowStep;
use Illuminate\Database\Seeder;

/**
 * زرع مسارات الموافقات (Phase 5): مسير واحد بأنشطة 3-5 خطوات لكل نوع
 * من ثلاثة أنواع. أسماء الأخطاق والأهداف حقيقية (أدوار Spatie المسجّلة
 * + أقسام فعلية). التكرار آمن: firstOrCreate للمسير ثم لكل خطوة بترتيبها.
 */
class TrxWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        // [slug النوع, اسم المسير, [ [اسم_الخطوة, نوع_الهدف, قيمة], ... ]]
        $plan = [
            ['conversion', 'مسير موافقات التحويل', [
                ['مراجعة أولى', 'role', 'reviewer'],
                ['اعتماد المدير', 'role', 'dept_manager'],
                ['التنفيذ المالي', 'department', 'الإدارة المالية'],
            ]],
            ['purchase', 'مسير موافقات الشراء', [
                ['طلب عروض الأسعار', 'department', 'المشتريات والمخازن'],
                ['مراجعة مالية', 'role', 'reviewer'],
                ['اعتماد المدير المالي', 'role', 'dept_manager'],
                ['اعتماد نهائي', 'role', 'university_admin'],
            ]],
            ['contract', 'مسير موافقات التعاقد', [
                ['مراجعة قانونية', 'department', 'الشؤون القانونية'],
                ['مراجعة مالية', 'role', 'reviewer'],
                ['اعتماد نهائي', 'role', 'university_admin'],
            ]],
        ];

        foreach ($plan as [$slug, $workflowName, $steps]) {
            $type = TrxType::where('slug', $slug)->first();
            if ($type === null) {
                continue;
            }

            $workflow = TrxWorkflow::firstOrCreate(
                ['trx_type_id' => $type->id, 'name' => $workflowName],
                ['is_active' => true, 'version' => 1]
            );

            // لا نعيد بناء خطوات موجودة أبدًا (التكرار الآمن).
            if ($workflow->steps()->exists()) {
                continue;
            }

            $order = 1;
            foreach ($steps as [$name, $targetType, $target]) {
                $attrs = [
                    'step_order' => $order,
                    'name_ar' => $name,
                    'target_type' => $targetType,
                    'target_dept_id' => null,
                    'target_role' => null,
                    'target_user_id' => null,
                ];

                if ($targetType === 'role') {
                    $attrs['target_role'] = $target;
                } elseif ($targetType === 'department') {
                    $dept = Department::where('name', $target)->first();
                    if ($dept === null) {
                        $order++; // القسم غير مزروع — نتخطى الخطوة مع إبقاء الترتيب سليمًا
                        continue;
                    }
                    $attrs['target_dept_id'] = $dept->id;
                }

                $workflow->steps()->create($attrs);
                $order++;
            }
        }

        $this->command?->info(
            'Workflows: '.TrxWorkflow::count().' | Steps: '.TrxWorkflowStep::count()
        );
    }
}
