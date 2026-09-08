<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Service;

use Cms_Service_Menu;
use Media_Service_Media;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_Media;
use Tiger_Model_Menu;

/**
 * Cross-tenant isolation for the two services that enforced the admin ROLE but never the OWNERSHIP of
 * the row being touched (TIGER-64, TIGER-65).
 *
 * The shipped ACL grants `admin` on these services, and an admin's role is derived from their OWN
 * membership — it says nothing about whose data the request names. So an admin in org A could pass
 * org B's `media_id` (or `org_id`) and read, modify or destroy it, holding no membership in B at all.
 *
 * Each test drives the REAL service with a signed-in org-A identity against seeded org-B rows, and
 * asserts both halves: A is refused, and B's row is untouched. The same-tenant positive controls
 * matter just as much — a scoping fix that denied everything would also "pass" the denial cases.
 */
#[CoversClass(Cms_Service_Menu::class)]
#[CoversClass(Media_Service_Media::class)]
final class TenantScopingTest extends IntegrationTestCase
{
    private const ORG_A = 'org-tenant-a';
    private const ORG_B = 'org-tenant-b';

    /** Sign in as an admin whose ONLY membership is org A. */
    private function asAdminOfA(): void
    {
        $this->login('user-tenant-a', self::ORG_A, 'admin');
    }

    private function seedMedia(string $orgId, string $filename): string
    {
        return (new Tiger_Model_Media())->insert([
            'org_id'      => $orgId,
            'disk'        => 'local',
            'storage_key' => 'probe/' . $orgId . '/' . $filename,
            'visibility'  => Tiger_Model_Media::VISIBILITY_PRIVATE,
            'kind'        => Tiger_Model_Media::KIND_DOCUMENT,
            'mime_type'   => 'application/pdf',
            'filename'    => $filename,
            'title'       => 'Owned by ' . $orgId,
            'file_size'   => 1024,
            'scan_status' => Tiger_Model_Media::SCAN_CLEAN,
        ]);
    }

    private function seedMenu(string $orgId, string $menuKey): void
    {
        (new Tiger_Model_Menu())->insert([
            'org_id'   => $orgId,
            'menu_key' => $menuKey,
            'label'    => 'Private nav',
            'sort_order' => 0,
            'status'   => 'published',
        ]);
    }

    // ---- media (TIGER-64) ------------------------------------------------------

    #[Test]
    public function an_admin_cannot_update_another_tenants_media(): void
    {
        $idB = $this->seedMedia(self::ORG_B, 'secret-b.pdf');
        $this->asAdminOfA();

        $svc = new Media_Service_Media();
        $svc->update(['media_id' => $idB, 'title' => 'Changed by tenant A']);

        $this->assertSame(0, (int) $svc->getResponse()->result, 'the request is refused');
        $this->assertSame('Owned by ' . self::ORG_B,
            (string) (new Tiger_Model_Media())->findById($idB)->title, "B's row is untouched");
    }

    #[Test]
    public function an_admin_cannot_delete_another_tenants_media(): void
    {
        // delete() destroys the stored bytes BEFORE soft-deleting, so this one is not undoable.
        $idB = $this->seedMedia(self::ORG_B, 'delete-me-b.pdf');
        $this->asAdminOfA();

        $svc = new Media_Service_Media();
        $svc->delete(['media_id' => $idB]);

        $this->assertSame(0, (int) $svc->getResponse()->result);
        $this->assertSame(0, (int) (new Tiger_Model_Media())->findById($idB)->deleted, "B's row survives");
    }

    #[Test]
    public function an_admin_can_still_update_their_own_media(): void
    {
        $idA = $this->seedMedia(self::ORG_A, 'mine-a.pdf');
        $this->asAdminOfA();

        $svc = new Media_Service_Media();
        $svc->update(['media_id' => $idA, 'title' => 'Renamed by its owner']);

        $this->assertSame(1, (int) $svc->getResponse()->result, 'same-tenant work still succeeds');
        $this->assertSame('Renamed by its owner', (string) (new Tiger_Model_Media())->findById($idA)->title);
    }

    #[Test]
    public function the_media_library_does_not_list_another_tenants_rows(): void
    {
        // The listing is also how a caller LEARNS a foreign media_id in the first place.
        $idB = $this->seedMedia(self::ORG_B, 'listed-b.pdf');
        $idA = $this->seedMedia(self::ORG_A, 'listed-a.pdf');
        $this->asAdminOfA();

        $rows = (new Tiger_Model_Media())->datatable(['org_id' => self::ORG_A, 'limit' => 100]);
        $ids  = array_map(static fn($r) => (string) $r['media_id'], $rows['rows']);

        $this->assertContains($idA, $ids, 'own media is listed');
        $this->assertNotContains($idB, $ids, "another tenant's media is not");
    }

    // ---- cms menus (TIGER-65) --------------------------------------------------

    #[Test]
    public function an_admin_cannot_delete_another_tenants_menu(): void
    {
        $this->seedMenu(self::ORG_B, 'private-nav');
        $this->asAdminOfA();

        $svc = new Cms_Service_Menu();
        $svc->deleteMenu(['menu_key' => 'private-nav', 'org_id' => self::ORG_B]);

        $this->assertSame(0, (int) $svc->getResponse()->result, 'a foreign org_id is refused');

        $model = new Tiger_Model_Menu();
        $rows  = $model->fetchAll($model->activeSelect()
            ->where('org_id = ?', self::ORG_B)->where('menu_key = ?', 'private-nav'));
        $this->assertCount(1, $rows, "B's menu is untouched");
    }

    #[Test]
    public function an_admin_can_still_delete_a_menu_in_their_own_scope(): void
    {
        $this->seedMenu(self::ORG_A, 'own-nav');
        $this->asAdminOfA();

        $svc = new Cms_Service_Menu();
        $svc->deleteMenu(['menu_key' => 'own-nav', 'org_id' => self::ORG_A]);

        $this->assertSame(1, (int) $svc->getResponse()->result);
        $model = new Tiger_Model_Menu();
        $this->assertCount(0, $model->fetchAll($model->activeSelect()
            ->where('org_id = ?', self::ORG_A)->where('menu_key = ?', 'own-nav')));
    }

    #[Test]
    public function the_global_menu_scope_remains_editable(): void
    {
        // MenuController pins the editor to '' ("per-tenant menu editing is a later concern"), so
        // locking mutations to the caller's own org alone would break the shipping admin screen.
        $this->seedMenu('', 'global-nav');
        $this->asAdminOfA();

        $svc = new Cms_Service_Menu();
        $svc->deleteMenu(['menu_key' => 'global-nav', 'org_id' => '']);

        $this->assertSame(1, (int) $svc->getResponse()->result, 'the global scope still works');
    }
}
