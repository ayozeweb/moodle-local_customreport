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
 * XML export of the follow-up report.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\export;

/**
 * Renders the follow-up report as XML following docs/xml-schema.md (validated by docs/followup.xsd).
 */
class xml {
    /** @var string XML namespace. */
    const NS = 'urn:moodle:local_customreport:followup:1';

    /**
     * Render.
     *
     * @param array $data Output of \local_customreport\report\followup::build().
     * @param int|null $now Generation time.
     * @return string
     */
    public static function render(array $data, ?int $now = null): string {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $root = $doc->createElementNS(self::NS, 'followup');
        $root->setAttribute('version', '1');
        $root->setAttribute('generated', gmdate('Y-m-d\TH:i:s\Z', $now ?? time()));
        $root->setAttribute('profile', $data['profile']->shortname);
        $doc->appendChild($root);

        $action = $data['action'];
        $el = self::child($doc, $root, 'action');
        $fields = ['actionorigin' => 'origin', 'actioncode' => 'code', 'centercode' => 'centercode', 'areacode' => 'areacode',
            'specialtycode' => 'specialtycode', 'name' => 'name', 'authority' => 'authority', 'modality' => 'modality'];
        foreach ($fields as $field => $tag) {
            self::child($doc, $el, $tag, (string) $action->$field);
        }
        self::child($doc, $el, 'situation', (string) (int) $action->situation);
        self::child($doc, $el, 'hours', (string) (int) $action->hours);
        foreach (['start', 'end'] as $edge) {
            $real = (int) $action->{$edge . 'real'};
            $date = $real ?: (int) $action->{$edge . 'planned'};
            $d = self::child($doc, $el, $edge . 'date', $date ? gmdate('Y-m-d', $date) : '');
            $d->setAttribute('type', $real ? 'real' : 'planned');
        }

        $filters = self::child($doc, $root, 'filters');
        self::child($doc, $filters, 'from', $data['filters']['from'] ? gmdate('Y-m-d', $data['filters']['from']) : '');
        self::child($doc, $filters, 'to', $data['filters']['to'] ? gmdate('Y-m-d', $data['filters']['to']) : '');
        self::child($doc, $filters, 'nodeid', (string) $data['filters']['nodeid']);

        $summary = self::child($doc, $root, 'summary');
        foreach ($data['summary'] as $key => $value) {
            self::child($doc, $summary, $key, $value === null ? '' : (string) $value);
        }

        $nodes = self::child($doc, $root, 'nodes');
        foreach ($data['nodes'] as $node) {
            $n = self::child($doc, $nodes, 'node');
            $n->setAttribute('id', (string) $node['nodeid']);
            $n->setAttribute('type', $node['type']);
            foreach (['code', 'name', 'hours', 'passed', 'passedpercent', 'seconds', 'avgseconds'] as $key) {
                self::child($doc, $n, $key, (string) $node[$key]);
            }
        }

        $areas = self::child($doc, $root, 'areas');
        foreach ($data['areas'] as $area) {
            $a = self::child($doc, $areas, 'area');
            $a->setAttribute('component', $area['component']);
            foreach (['accesses', 'accessespercent', 'seconds', 'participations'] as $key) {
                self::child($doc, $a, $key, (string) $area[$key]);
            }
        }

        $learners = self::child($doc, $root, 'learners');
        foreach ($data['learners'] as $learner) {
            $l = self::child($doc, $learners, 'learner');
            $l->setAttribute('id', (string) $learner['userid']);
            $l->setAttribute('status', $learner['status']);
            self::child($doc, $l, 'fullname', $learner['fullname']);
            foreach (['accesses', 'participations', 'seconds', 'firstaccess', 'lastaccess', 'total', 'done', 'percent'] as $key) {
                self::child($doc, $l, $key, (string) $learner[$key]);
            }
            foreach (['processavg', 'final1', 'final2', 'score', 'call'] as $key) {
                self::child($doc, $l, $key, $learner[$key] === null ? '' : (string) $learner[$key]);
            }
            self::child($doc, $l, 'passed', $learner['passed'] ? 'true' : 'false');
        }
        return $doc->saveXML();
    }

    /**
     * Append a child element.
     *
     * @param \DOMDocument $doc
     * @param \DOMElement $parent
     * @param string $name
     * @param string|null $text
     * @return \DOMElement
     */
    private static function child(\DOMDocument $doc, \DOMElement $parent, string $name, ?string $text = null): \DOMElement {
        $el = $doc->createElementNS(self::NS, $name);
        if ($text !== null) {
            $el->appendChild($doc->createTextNode($text));
        }
        return $parent->appendChild($el);
    }
}
