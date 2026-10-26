<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Department;
use App\Models\User;
use App\Support\ItqanData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wiring regression for the three organization-structure UI bugs reported
 * from the live app (colleges table always empty, the college column always
 * "—", and add-user answering "password required" while it was typed):
 *
 * - the payload really carries colleges + the department's collegeId
 *   (server half), the page embeds it, and
 * - the JS bridge exposes ITQAN.colleges, and the user form submits the
 *   typed password (frontend halves that PHPUnit can only pin by content).
 */
class UiWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_structure_reaches_the_frontend(): void
    {
        $admin = User::factory()->admin()->create();
        $college = College::factory()->create(['name' => 'كلية الاختبار']);
        Department::create(['name' => 'قسم الاختبار', 'college_id' => $college->id]);

        // Server half — the exact shape window.ITQAN_DATA receives.
        $payload = ItqanData::for($admin);
        $this->assertSame([['id' => $college->id, 'name' => 'كلية الاختبار']], $payload['colleges']);
        $this->assertSame($college->id, $payload['departments'][0]['collegeId']);

        // The departments page embeds that payload (colleges key present).
        $this->actingAs($admin)->get('/departments')->assertOk()->assertSee('colleges', false);

        // Frontend halves — the data bridge exposes colleges, and the
        // add/edit-user form sends the typed password.
        $bridge = (string) file_get_contents(public_path('js/departments.js'));
        $this->assertStringContainsString('ITQAN.colleges', $bridge);
        $this->assertStringContainsString('D.colleges', $bridge);

        $forms = (string) file_get_contents(public_path('js/forms.js'));
        $this->assertStringContainsString('rec.password', $forms);
    }
}
