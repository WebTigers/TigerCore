<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Ally_Service_Scan — the /api behind the TigerAlly accessibility inspector.
 *
 * READ-ONLY, admin-gated. It renders content and runs Tiger_Ally over the HTML; it never edits
 * anything. Three actions: `scan` (a pasted HTML blob OR one CMS page by id → a findings report),
 * `pages` (the scannable CMS page list for the picker), and `scanAll` (every page → a per-page
 * pass/fail roll-up). `scan` is a Forge read-verb, so the in-app AI agent can run an a11y check on
 * demand (as the acting admin) with no approval step.
 *
 * @api
 */
class Ally_Service_Scan extends Tiger_Service_Service
{
    /**
     * Inspect pasted HTML (param `html`) or a rendered CMS page (param `page_id`).
     *
     * @param  array $params html | page_id
     * @return void
     */
    public function scan(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $html   = (string) ($params['html'] ?? '');
        $pageId = trim((string) ($params['page_id'] ?? ''));
        $source = null;

        if ($pageId !== '') {
            $row = (new Tiger_Model_Page())->findById($pageId);
            if (!$row) { $this->_error('ally.scan.page_not_found'); return; }
            try {
                $html = (new Tiger_Cms_Renderer())->renderBody($row->body, $row->format, ['page' => $row]);
            } catch (Throwable $e) {
                Tiger_Log::warn('ally.scan.render_failed', ['page_id' => $pageId, 'error' => $e->getMessage()]);
                $this->_error('ally.scan.render_failed');
                return;
            }
            $source = ['page_id' => $pageId, 'title' => (string) $row->title, 'slug' => (string) $row->slug, 'format' => (string) $row->format];
        }

        if (trim($html) === '') { $this->_error('ally.scan.empty'); return; }

        $report = Tiger_Ally::inspect($html);
        $report['source'] = $source;   // null = pasted HTML
        $this->_success($report, 'ally.scan.done');
    }

    /**
     * The scannable CMS pages (type=page), for the picker + scanAll.
     *
     * @param  array $params (unused)
     * @return void
     */
    public function pages(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $this->_success(['pages' => $this->_pageList()], 'core.api.success');
    }

    /**
     * Scan every CMS page and return a per-page roll-up (pass/fail + error/warning counts).
     *
     * @param  array $params (unused)
     * @return void
     */
    public function scanAll(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $renderer = new Tiger_Cms_Renderer();
        $results  = [];
        $totals   = ['pages' => 0, 'passed' => 0, 'error' => 0, 'warning' => 0];
        foreach ($this->_pageList() as $p) {
            $row = (new Tiger_Model_Page())->findById($p['page_id']);
            if (!$row) { continue; }
            try {
                $html = $renderer->renderBody($row->body, $row->format, ['page' => $row]);
            } catch (Throwable $e) {
                $results[] = $p + ['passed' => null, 'error' => 0, 'warning' => 0, 'skipped' => true];
                continue;
            }
            $r = Tiger_Ally::inspect($html);
            $totals['pages']++;
            $totals['error']   += $r['summary']['error'];
            $totals['warning'] += $r['summary']['warning'];
            if ($r['passed']) { $totals['passed']++; }
            $results[] = $p + ['passed' => $r['passed'], 'error' => $r['summary']['error'], 'warning' => $r['summary']['warning']];
        }

        $this->_success(['totals' => $totals, 'results' => $results], 'ally.scan.done');
    }

    /**
     * Active CMS pages (type=page) as [page_id, title, slug, format, locale].
     *
     * @return array<int,array<string,string>>
     */
    private function _pageList(): array
    {
        $m  = new Tiger_Model_Page();
        $db = $m->getAdapter();
        return $db->fetchAll(
            $db->select()
               ->from('page', ['page_id', 'title', 'slug', 'format', 'locale'])
               ->where('type = ?', Tiger_Model_Page::TYPE_PAGE)
               ->where('deleted = ?', 0)
               ->order(['slug ASC', 'locale ASC'])
        );
    }
}
