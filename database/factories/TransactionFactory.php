<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Transaction;
use App\Models\TrxType;
use App\Models\TrxWorkflow;
use App\Models\User;
use App\Support\TransactionTypes;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * عدّاد متسلسل ثابت داخل التشغيل الواحد — يضمن تفرد رقم المعاملة
     * TRX-YYYY-NNNN ويبني على آخر رقم موجود في القاعدة (نفس منطق
     * App\Services\TransactionNumber تمامًا).
     */
    protected static ?int $sequence = null;

    protected $model = Transaction::class;

    /**
     * الحالة الافتراضية: معاملة "قيد الانتظار" بحقول عربية واقعية.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_number' => static::nextNumber(),
            'subject' => fake()->randomElement([
                'طلب شراء أجهزة حاسب للمختبر',
                'تحويل مبلغ إلى حساب العامل',
                'دفع فاتورة الكهرباء للعام الحالي',
                'تعاقد مع شركة للصيانة الدورية',
                'طلب تجديد رخصة البرامج',
                'شراء أثاث لقاعات التدريس',
                'سداد مستحقات نقل موظف',
                'تأمين أجهزة شبكات القاعات',
                'طلب اعتماد عقد توريد مستلزمات',
                'تحويل مصروف مبادرة طلابية',
            ]),
            'type' => fake()->randomElement(TransactionTypes::SLUGS),
            'date' => fake()->dateTimeBetween('-180 days', 'now')->format('Y-m-d'),
            'sender' => fake()->randomElement([
                'عمادة شئون الطلاب',
                'قسم تقنية المعلومات',
                'الإدارة المالية',
                'وحدة المشتريات',
                'عمادة الدراسات العليا',
                null,
            ]),
            'recipient' => fake()->randomElement([
                'شركة النور للتجارة',
                'مؤسسة الوفاق للمقاولات',
                'بنك السلام',
                'مورد شبكة المحامين',
                'حساب موظف بالبنك الأهلي',
            ]),
            'amount' => fake()->randomFloat(2, 500, 90000),
            'description' => fake()->optional(0.6)->sentence(8),
            'priority' => 'normal',
            'status' => 'pending',
            'dept_id' => Department::factory(),
            'created_by' => User::factory(),
            'assigned_to' => null,
            'form_data' => null,
            'workflow_data' => null,
        ];
    }

    /* ------------------------------------------------------ حالات (states) */

    /** مسودة — قبل الإرسال. */
    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    /** مكتملة (معتمدة). */
    public function done(): static
    {
        return $this->state(fn () => ['status' => 'done']);
    }

    /** مرفوضة. */
    public function rejected(): static
    {
        return $this->state(fn () => ['status' => 'rejected']);
    }

    /** معادة للاكمال. */
    public function returned(): static
    {
        return $this->state(fn () => ['status' => 'returned']);
    }

    /** أولوية عاجلة (عمود priority). */
    public function urgent(): static
    {
        return $this->state(fn () => ['priority' => 'urgent']);
    }

    /** مع معين عليه موظف (assigned_to). */
    public function assigned(): static
    {
        return $this->state(fn () => ['assigned_to' => User::factory()]);
    }

    /** حقلي نموذج ديناميكي (form_data مفتاحها معرّف حقل حقيقي). */
    public function withFormData(array $formData): static
    {
        return $this->state(fn () => ['form_data' => $formData === [] ? null : $formData]);
    }

    /** لقطة سير الموافقات (workflow_data — نفس شكل WorkflowService). */
    public function withWorkflowData(array $snapshot): static
    {
        return $this->state(fn () => ['workflow_data' => $snapshot]);
    }

    /**
     * حالة الزرع التجريبي (تُستخدم من TransactionSeeder فقط): كل ما يحتاجه
     * سطر واقعي — نوع فعّال حقيقي بنصوصه المطابقة، حالة موزّعة وزنيًا، منشئ
     * من حسابات حقيقية موجودة (لا يُنشأ حساب جديد)، معين غير المنشئ، حقول
     * نموذج بمفاتيحها الحقيقية، لقطة سير موافقات، وتاريخ إنشاء متدرج.
     *
     * لا تُستدعَد من الاختبارات (تلك تبني بياناتها صراحة عبر definition()).
     */
    public function seeded(): static
    {
        return $this->state(function (): array {
            $types = TrxType::where('is_active', true)->with('activeFields')->get();
            $type = $types->isNotEmpty() ? $types->random() : null;
            $slug = $type?->slug ?? fake()->randomElement(TransactionTypes::SLUGS);
            $status = $this->pickStatus();

            // منشئ واقعي: موظف أو مراجع من الحسابات الموجودة فقط.
            $employees = User::whereIn('role', ['employee', 'reviewer'])->get(['id', 'department_id']);
            $creator = $employees->isNotEmpty() ? $employees->random() : null;
            $departmentId = $creator?->department_id
                ?? Department::query()->inRandomOrder()->value('id');

            // معين: لغير المسودة غالبًا مراجع/مدير (ليس المنشئ أبدًا).
            $assignee = null;
            if ($status !== 'draft') {
                $approvers = User::whereIn('role', ['system_admin', 'university_admin', 'dept_manager', 'reviewer'])
                    ->where('id', '!=', $creator?->id)
                    ->get(['id']);

                if ($approvers->isNotEmpty() && fake()->boolean(70)) {
                    $assignee = $approvers->random();
                }
            }

            $when = now()->subDays(fake()->numberBetween(0, 179))
                ->subMinutes(fake()->numberBetween(0, 1439));

            return [
                'subject' => $this->subjectFor($slug),
                'type' => $slug,
                'sender' => $this->senderFor($slug),
                'recipient' => $this->recipientFor($slug),
                'priority' => fake()->boolean(20) ? 'urgent' : 'normal',
                'status' => $status,
                'dept_id' => $departmentId,
                'created_by' => $creator?->id,
                'assigned_to' => $assignee?->id,
                'form_data' => $type ? $this->formDataFor($type) : null,
                'workflow_data' => $status === 'draft' ? null : $this->snapshotFor($type, $status),
                'created_at' => $when,
                'updated_at' => $when,
            ];
        });
    }

    /* ---------------------------------------------------- أدوات الزرع */

    /** توزيع مرجّح للحالات الخمس الحقيقية. */
    private function pickStatus(): string
    {
        $r = fake()->numberBetween(1, 100);

        return match (true) {
            $r <= 35 => 'done',
            $r <= 65 => 'pending',
            $r <= 77 => 'rejected',
            $r <= 87 => 'draft',
            default => 'returned',
        };
    }

    private function subjectFor(string $slug): string
    {
        return match ($slug) {
            'conversion' => fake()->randomElement([
                'تحويل مبلغ لمورد خدمات',
                'تحويل مستحقات موظف',
                'تحويل مصروف مبادرة طلابية',
            ]),
            'purchase' => fake()->randomElement([
                'شراء أجهزة حاسب للمختبر',
                'شراء أثاث لقاعات التدريس',
                'شراء لوحات إنذار الحريق',
            ]),
            'bills' => fake()->randomElement([
                'دفع فاتورة الكهرباء',
                'دفع فاتورة الإنترنت الشهري',
                'سداد مستحقات نقل موظف',
            ]),
            'contract' => fake()->randomElement([
                'تعاقد مع شركة صيانة',
                'تعاقد توريد مستلزمات مكتبية',
                'تجديد عقد أمن ونظافة',
            ]),
            'travel' => fake()->randomElement([
                'طلب سفر مهمة علمية',
                'سفر لحضور مؤتمر تعليمي',
            ]),
            'maintenance' => fake()->randomElement([
                'صيانة وحدات تحكم المختبر',
                'صيانة أجهزة العرض',
            ]),
            default => 'معاملة — '.fake()->sentence(3),
        };
    }

    private function senderFor(string $slug): ?string
    {
        return match ($slug) {
            'conversion' => 'الإدارة المالية',
            'purchase' => 'وحدة المشتريات',
            'contract' => 'الشؤون القانونية',
            default => fake()->randomElement(['عمادة شئون الطلاب', 'قسم تقنية المعلومات', null]),
        };
    }

    private function recipientFor(string $slug): string
    {
        return match ($slug) {
            'conversion' => fake()->randomElement(['بنك السلام', 'البنك الأهلي', 'حساب موظف']),
            'purchase' => fake()->randomElement(['شركة النور للتجارة', 'مؤسسة الوفاق للمقاولات']),
            'bills' => fake()->randomElement(['شركة الكهرباء', 'شركة الاتصالات']),
            'contract' => fake()->randomElement(['شركة الأمانة للصيانة', 'مجموعة الفجر التجارية']),
            default => fake()->company(),
        };
    }

    /**
     * ملء حقول النوع الفعلية (إن وجدت) بمفاتيح حقيقية = معرّفات الحقول،
     * وقيم مطابقة لنوع كل حقل (select من خياراته الفعلية).
     */
    private function formDataFor(TrxType $type): ?array
    {
        $fields = $type->activeFields;
        if ($fields->isEmpty()) {
            return null;
        }

        $data = [];
        foreach ($fields as $field) {
            $data[$field->id] = match ($field->field_type) {
                'number' => fake()->numberBetween(100, 99999),
                'date' => fake()->dateTimeBetween('-60 days', '+60 days')->format('Y-m-d'),
                'select' => ($field->options[0]['ar'] ?? null) ?? '—',
                'checkbox' => fake()->boolean(),
                'textarea' => fake()->sentence(8),
                'file' => fake()->randomElement(['مرفق-واحد.pdf', 'مرفق-اثنان.pdf']),
                default => fake()->randomElement(['شركة الأمانة التجارية', 'رقم داخلي '.fake()->numerify('####'), 'موظف مكلف']),
            };
        }

        return $data;
    }

    /**
     * لقطة سير الموافقات بنفس هيئة WorkflowService:
     * {id, version, started_at, current_step, total, steps[], completed[]}
     * مع سلوك منطق: done ⇒ كل الخطوات معتمدة، pending ⇒ خطوة حالية،
     * rejected/returned ⇒ قرار مسجّل على خطوة حالية. بلا مسير ⇒ null
     * (سلوك الخطوة الواحدة كما في النظام).
     */
    private function snapshotFor(?TrxType $type, string $status): ?array
    {
        $workflow = TrxWorkflow::where('is_active', true)
            ->where('trx_type_id', $type?->id ?? 0)
            ->with('steps')
            ->get()
            ->first(fn (TrxWorkflow $w) => $w->steps->isNotEmpty());

        if ($workflow === null) {
            return null;
        }

        $steps = $workflow->steps->values();
        $total = $steps->count();
        $approverNames = User::whereIn('role', ['system_admin', 'university_admin', 'dept_manager', 'reviewer'])
            ->pluck('name');
        $at = now()->subDays(fake()->numberBetween(1, 90))->toDateTimeString();

        $mkStep = fn (int $index) => [
            'name' => $steps[$index]->name_ar,
            'target_type' => $steps[$index]->target_type,
            'target_dept' => $steps[$index]->target_dept_id,
            'target_role' => $steps[$index]->target_role,
            'target_user' => $steps[$index]->target_user_id,
        ];

        $completed = [];
        $currentStep = 1;

        if ($status === 'done') {
            foreach ($steps as $i => $step) {
                $completed[] = [
                    'step' => $i + 1,
                    'name' => $step->name_ar,
                    'by' => $approverNames->isNotEmpty() ? $approverNames->random() : '—',
                    'at' => $at,
                    'decision' => 'approve',
                    'reason' => null,
                ];
            }
            $currentStep = $total;
        } else {
            // pending | rejected | returned — خطوات سابقة معتمدة +
            // خطوة حالية بانتظار القرار أو حاملة قرار نهائي/إعادة.
            $currentStep = fake()->numberBetween(1, $total);
            for ($i = 1; $i < $currentStep; $i++) {
                $completed[] = [
                    'step' => $i,
                    'name' => $steps[$i - 1]->name_ar,
                    'by' => $approverNames->isNotEmpty() ? $approverNames->random() : '—',
                    'at' => $at,
                    'decision' => 'approve',
                    'reason' => null,
                ];
            }

            if ($status !== 'pending') {
                $completed[] = [
                    'step' => $currentStep,
                    'name' => $steps[$currentStep - 1]->name_ar,
                    'by' => $approverNames->isNotEmpty() ? $approverNames->random() : '—',
                    'at' => $at,
                    'decision' => $status === 'rejected' ? 'reject' : 'return',
                    'reason' => $status === 'rejected'
                        ? 'المبلغ غير مدعوم بمستندات كافية'
                        : 'نواقص في المستندات المرفقة',
                ];
            }
        }

        return [
            'id' => $workflow->id,
            'version' => $workflow->version,
            'started_at' => $at,
            'current_step' => $currentStep,
            'total' => $total,
            'steps' => collect(range(0, $total - 1))->map($mkStep)->values()->all(),
            'completed' => $completed,
        ];
    }

    /* -------------------------------------------------------- أدوات */

    /**
     * توليد الرقم التسلسلي بنفس منطق TransactionNumber::next():
     * TRX-YYYY-NNNN مبنيًا على أعلى رقم موجود للسنة الحالية.
     */
    protected static function nextNumber(): string
    {
        $year = date('Y');
        $prefix = 'TRX-'.$year.'-';

        if (static::$sequence === null) {
            $last = Transaction::query()
                ->where('transaction_number', 'like', $prefix.'%')
                ->orderByDesc('transaction_number')
                ->value('transaction_number');

            static::$sequence = $last ? ((int) substr($last, -4)) : 0;
        }

        return $prefix.str_pad((string) ++static::$sequence, 4, '0', STR_PAD_LEFT);
    }
}
