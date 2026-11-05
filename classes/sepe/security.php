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
 * WS-Security UsernameToken Profile 1.0 (wsse:PasswordText) authentication.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\sepe;

/**
 * Authenticates web service requests against the credentials of each competent administration.
 */
class security {
    /** @var string WS-Security extension namespace. */
    const NS_WSSE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

    /** @var string Password type URI for plain text passwords. */
    const PASSWORD_TEXT = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText';

    /**
     * Read the UsernameToken of a SOAP envelope.
     *
     * @param \DOMDocument $doc
     * @return array|null ['username', 'password', 'type'] or null when there is no token.
     */
    public static function read_token(\DOMDocument $doc): ?array {
        $tokens = $doc->getElementsByTagNameNS(self::NS_WSSE, 'UsernameToken');
        if (!$tokens->length) {
            return null;
        }
        $token = $tokens->item(0);
        $user = $token->getElementsByTagNameNS(self::NS_WSSE, 'Username')->item(0);
        $pass = $token->getElementsByTagNameNS(self::NS_WSSE, 'Password')->item(0);
        if (!$user || !$pass) {
            return null;
        }
        $type = $pass->getAttribute('Type');
        return ['username' => trim($user->textContent), 'password' => $pass->textContent,
            'type' => $type === '' ? self::PASSWORD_TEXT : $type];
    }

    /**
     * Authenticate a token. Only PasswordText is accepted (Annex V.A).
     *
     * @param array|null $token
     * @param int|null $now
     * @return \stdClass|null The credential record, or null when authentication fails.
     */
    public static function authenticate(?array $token, ?int $now = null): ?\stdClass {
        global $DB;
        if (!$token || $token['type'] !== self::PASSWORD_TEXT || $token['username'] === '' || $token['password'] === '') {
            return null;
        }
        $cred = $DB->get_record('local_customreport_wscred', ['username' => $token['username'], 'enabled' => 1]);
        if (!$cred) {
            // Spend the same time as a real check so that unknown users cannot be told apart by timing.
            password_verify($token['password'], '$2y$10$abcdefghijklmnopqrstuuabcdefghijklmnopqrstuvwxyz01234');
            return null;
        }
        if (!password_verify($token['password'], $cred->passwordhash)) {
            return null;
        }
        $DB->set_field('local_customreport_wscred', 'lastused', $now ?? time(), ['id' => $cred->id]);
        return $cred;
    }

    /**
     * Create or replace the credentials of an administration.
     *
     * @param string $authority
     * @param string $username
     * @param string $password
     * @return int Credential id.
     */
    public static function save_credential(string $authority, string $username, string $password): int {
        global $DB;
        $record = (object) ['authority' => $authority, 'username' => $username,
            'passwordhash' => password_hash($password, PASSWORD_DEFAULT), 'enabled' => 1];
        $existing = $DB->get_record('local_customreport_wscred', ['username' => $username]);
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_customreport_wscred', $record);
            return (int) $existing->id;
        }
        $record->timecreated = time();
        $record->lastused = 0;
        return (int) $DB->insert_record('local_customreport_wscred', $record);
    }
}
