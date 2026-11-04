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
 * Regulatory profile manager: validation, import and export of JSON profiles.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

use moodle_exception;

/**
 * Manages versioned JSON regulatory profiles.
 */
class profile_manager {
    /** @var int Supported JSON schema version. */
    const SCHEMA = 1;

    /** @var string[] Built-in profile files in presets/. */
    const BUILTIN = ['sepe_sce_tms369', 'generico', 'empresa'];

    /**
     * Validate a decoded profile.
     *
     * @param mixed $data Decoded JSON.
     * @return string[] List of problems; empty when valid.
     */
    public static function validate($data): array {
        $errors = [];
        if (!is_object($data) && !is_array($data)) {
            return ['notanobject'];
        }
        $data = (object) $data;
        if (($data->schema ?? null) !== self::SCHEMA) {
            $errors[] = 'schema';
        }
        if (empty($data->shortname) || !is_string($data->shortname) || !preg_match('/^[a-z0-9_]{1,50}$/', $data->shortname)) {
            $errors[] = 'shortname';
        }
        if (empty($data->name) || !is_string($data->name)) {
            $errors[] = 'name';
        }
        if (!isset($data->version) || !is_int($data->version) || $data->version < 1) {
            $errors[] = 'version';
        }
        foreach (['passmark', 'minfinal'] as $key) {
            if (!isset($data->$key) || !is_numeric($data->$key) || $data->$key < 0 || $data->$key > 10) {
                $errors[] = $key;
            }
        }
        $weights = (object) ($data->weights ?? []);
        if (
            !isset($weights->process, $weights->final) || !is_numeric($weights->process) || !is_numeric($weights->final)
                || $weights->process < 0 || $weights->final < 0
                || abs($weights->process + $weights->final - 1) > 0.0001
        ) {
            $errors[] = 'weights';
        }
        if (!isset($data->attempts) || !is_int($data->attempts) || $data->attempts < 1 || $data->attempts > 2) {
            $errors[] = 'attempts';
        }
        foreach (['reports', 'columns', 'requiredfields'] as $key) {
            if (!isset($data->$key) || !is_array($data->$key) || array_filter($data->$key, fn($v) => !is_string($v))) {
                $errors[] = $key;
            }
        }
        $legal = (object) ($data->legaltexts ?? []);
        $consent = (object) ($legal->consent ?? []);
        if (empty($consent->en) || empty($consent->es)) {
            $errors[] = 'legaltexts';
        }
        return $errors;
    }

    /**
     * Import a profile from a JSON string (insert or update when the version is not older).
     *
     * @param string $json
     * @param bool $builtin Mark as built-in.
     * @return \stdClass The stored record.
     * @throws moodle_exception When the JSON is invalid.
     */
    public static function import_json(string $json, bool $builtin = false): \stdClass {
        global $DB;
        try {
            $data = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new moodle_exception('profile_invalidjson', 'local_customreport');
        }
        $errors = self::validate($data);
        if ($errors) {
            throw new moodle_exception('profile_invalid', 'local_customreport', '', implode(', ', $errors));
        }
        $now = time();
        $existing = $DB->get_record('local_customreport_profile', ['shortname' => $data->shortname]);
        $record = (object) [
            'shortname' => $data->shortname,
            'name' => $data->name,
            'version' => $data->version,
            'builtin' => $builtin ? 1 : 0,
            'config' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timemodified' => $now,
        ];
        if ($existing) {
            if ($data->version < $existing->version) {
                throw new moodle_exception('profile_older', 'local_customreport', '', $existing->version);
            }
            $record->id = $existing->id;
            $record->builtin = $existing->builtin || $builtin ? 1 : 0;
            $DB->update_record('local_customreport_profile', $record);
        } else {
            $record->timecreated = $now;
            $record->id = $DB->insert_record('local_customreport_profile', $record);
        }
        return $record;
    }

    /**
     * Load the built-in profiles shipped in presets/.
     */
    public static function install_builtin(): void {
        global $CFG;
        foreach (self::BUILTIN as $name) {
            $file = $CFG->dirroot . '/local/customreport/presets/' . $name . '.json';
            if (is_readable($file)) {
                self::import_json(file_get_contents($file), true);
            }
        }
    }

    /**
     * Get a decoded profile by shortname.
     *
     * @param string $shortname
     * @return \stdClass|null
     */
    public static function get(string $shortname): ?\stdClass {
        global $DB;
        $config = $DB->get_field('local_customreport_profile', 'config', ['shortname' => $shortname]);
        return $config ? json_decode($config) : null;
    }

    /**
     * Profile names indexed by shortname.
     *
     * @return string[]
     */
    public static function get_menu(): array {
        global $DB;
        return $DB->get_records_menu('local_customreport_profile', null, 'name', 'shortname, name');
    }

    /**
     * Export a profile as pretty JSON.
     *
     * @param string $shortname
     * @return string
     * @throws moodle_exception
     */
    public static function export_json(string $shortname): string {
        $profile = self::get($shortname);
        if (!$profile) {
            throw new moodle_exception('profile_notfound', 'local_customreport', '', $shortname);
        }
        return json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
