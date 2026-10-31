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
 * Cross-tenant behaviour of the services that enforced a ROLE but not the row's OWNERSHIP
 * (TIGER-64 media, TIGER-65 cms menu), as it stands after the ACL.md §1a conformance pass (TIGER-410).
 *
 * §1a makes "reach every org" a CAPABILITY (`Tiger_Service_Service::RESOURCE_ALL_ORGS`), not a role
 * compare. Core's acl.ini grants it at `admin`, so the cut line sits between `supermanager` (tenant /
 * org-tree scoped) and `admin` (app-wide). A service asks `_inOrgScope($row->org_id)` — the caller's
 * READ scope — instead of "is this my org".
 *
 * What that means per service:
 *   - **Media** has been converted to the primitive. Its ACL grants the service at `admin`, and `admin`
 *     is exactly the tier that holds the all-orgs capability, so in core an admin legitimately manages
 *     EVERY org's media — administering orgs is the job. There is deliberately no admin-but-walled role
 *     for media in core; the tenant wall itself (a role WITHOUT the capability is scoped to its own org)
 *     is pinned at the primitive in Unit/Service/OrgScopeTest, which is where that decision lives.
 *   - **Cms menu** has NOT yet been converted (out of scope for TIGER-410), so it still hard-scopes and
 *     a foreign `org_id` is refused. Those cases pin its CURRENT behaviour until it adopts the primitive.
 *
 * Each test drives the REAL service with a signed-in org-A identity against seeded org-B rows. The
 * same-tenant positive controls matter just as much — an assertion that only checked the cross-org case
 * would also "pass" if the service were broken outright.
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
    public function an_app_wide_admin_can_update_another_orgs_media(): void
    {
        // §1a: `admin` holds the all-orgs capability in core, and media is an admin-gated feature, so an
        // admin administers every org's media. (A role WITHOUT the capability is walled — see OrgScopeTest.)
        $idB = $this->seedMedia(self::ORG_B, 'secret-b.pdf');
        $this->asAdminOfA();

        $svc = new Media_Service_Media();
        $svc->update(['media_id' => $idB, 'title' => 'Changed by the app admin']);

        $this->assertSame(1, (int) $svc->getResponse()->result, 'an app-wide admin reaches every org');
        $this->assertSame('Changed by the app admin',
            (string) (new Tiger_Model_Media())->findById($idB)->title, "B's row is updated");
    }

    #[Test]
    public function an_app_wide_admin_can_delete_another_orgs_media(): void
    {
        // delete() destroys the stored bytes BEFORE soft-deleting; an app-wide admin is entitled to.
        $idB = $this->seedMedia(self::ORG_B, 'delete-me-b.pdf');
        $this->asAdminOfA();

        $svc = new Media_Service_Media();
        $svc->delete(['media_id' => $idB]);

        $this->assertSame(1, (int) $svc->getResponse()->result);
        // findById hides soft-deleted by default, so include them to read the deleted flag.
        $this->assertSame(1, (int) (new Tiger_Model_Media())->findById($idB, true)->deleted, "B's row is removed");
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
