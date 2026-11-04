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
 * Institutional images for reports and prints.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * Logos uploaded by the administrator (the plugin ships no official logos).
 */
class branding {
    /** @var int Number of logo slots. */
    const SLOTS = 4;

    /**
     * URLs of the uploaded logos.
     *
     * @return array[] Each ['url' => string, 'alt' => string].
     */
    public static function logos(): array {
        $fs = get_file_storage();
        $context = \context_system::instance();
        $logos = [];
        for ($i = 1; $i <= self::SLOTS; $i++) {
            $files = $fs->get_area_files($context->id, 'local_customreport', 'logo' . $i, 0, 'itemid, filepath, filename', false);
            if ($file = reset($files)) {
                $url = \moodle_url::make_pluginfile_url(
                    $context->id,
                    'local_customreport',
                    'logo' . $i,
                    0,
                    $file->get_filepath(),
                    $file->get_filename()
                );
                $alt = get_config('local_customreport', 'logoalt' . $i) ?: $file->get_filename();
                $logos[] = ['url' => $url->out(), 'alt' => $alt];
            }
        }
        return $logos;
    }

    /**
     * Number of uploaded logos.
     *
     * @return int
     */
    public static function count(): int {
        return count(self::logos());
    }
}
