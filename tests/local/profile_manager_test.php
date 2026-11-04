<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for the regulatory profile manager.
 *
 * @package    local_customreport
 * @category   test
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * Tests for profile_manager.
 *
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_customreport\local\profile_manager::class)]
final class profile_manager_test extends \advanced_testcase {
    /**
     * A valid profile used as a base.
     *
     * @return array
     */
    private function valid(): array {
        return json_decode(file_get_contents(__DIR__ . '/../../presets/generico.json'), true);
    }

    /**
     * The three built-in profiles are installed with the plugin and are valid.
     */
    public function test_builtin_profiles_installed(): void {
        $this->resetAfterTest();
        profile_manager::install_builtin();
        foreach (profile_manager::BUILTIN as $shortname) {
            $profile = profile_manager::get($shortname);
            $this->assertNotNull($profile, $shortname);
            $this->assertSame([], profile_manager::validate($profile));
        }
    }

    /**
     * Default SEPE/SCE weights are 0.30 / 0.70 with a minimum final score of 5.
     */
    public function test_sepe_defaults(): void {
        $this->resetAfterTest();
        profile_manager::install_builtin();
        $profile = profile_manager::get('sepe_sce_tms369');
        $this->assertEquals(0.30, $profile->weights->process);
        $this->assertEquals(0.70, $profile->weights->final);
        $this->assertEquals(5, $profile->minfinal);
        $this->assertEquals(2, $profile->attempts);
    }

    /**
     * Invalid profiles are rejected.
     */
    public function test_validate_rejects_bad_data(): void {
        $data = $this->valid();
        $data['weights'] = ['process' => 0.5, 'final' => 0.6];
        $this->assertContains('weights', profile_manager::validate($data));

        $data = $this->valid();
        $data['shortname'] = 'Bad Name!';
        $this->assertContains('shortname', profile_manager::validate($data));

        $data = $this->valid();
        $data['schema'] = 99;
        $this->assertContains('schema', profile_manager::validate($data));

        $data = $this->valid();
        unset($data['legaltexts']['consent']['es']);
        $this->assertContains('legaltexts', profile_manager::validate($data));

        $this->assertSame(['notanobject'], profile_manager::validate('text'));
    }

    /**
     * Import creates, updates and refuses older versions.
     */
    public function test_import_versions(): void {
        $this->resetAfterTest();
        $data = $this->valid();
        $data['shortname'] = 'custom';
        $data['version'] = 2;
        $record = profile_manager::import_json(json_encode($data));
        $this->assertSame('custom', $record->shortname);

        $data['version'] = 3;
        $data['name'] = 'Custom v3';
        profile_manager::import_json(json_encode($data));
        $this->assertSame('Custom v3', profile_manager::get_menu()['custom']);

        $data['version'] = 1;
        $this->expectException(\moodle_exception::class);
        profile_manager::import_json(json_encode($data));
    }

    /**
     * Invalid JSON raises an exception.
     */
    public function test_import_invalid_json(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        profile_manager::import_json('{not json');
    }

    /**
     * Export then import round trip keeps the content.
     */
    public function test_export_roundtrip(): void {
        $this->resetAfterTest();
        profile_manager::install_builtin();
        $json = profile_manager::export_json('empresa');
        $this->assertEquals(profile_manager::get('empresa'), json_decode($json));
        $record = profile_manager::import_json($json);
        $this->assertSame('empresa', $record->shortname);
    }
}
