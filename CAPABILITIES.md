# Tiger — Capabilities

> **GENERATED** by `bin/build-capabilities.php` from class docblocks — **do not edit by hand**;
> run the generator. This is the agent's FIRST STOP: *"does X already exist, and where?"* Grep it
> before assuming something isn't built. `@api` = stable to build on; `@internal` = may change.
> Grouped by **capability** (across layers), not by directory.

**173 classes** across **31 capabilities** · **17 modules**. Full prose: [FEATURES.md](FEATURES.md) (what) · [ARCHITECTURE.md](ARCHITECTURE.md) (why). Not-yet-built: [BACKLOG.md](BACKLOG.md).

## Capabilities (`library/Tiger`)

### Kernel / bootstrap

- **Tiger_Application** `@api` — Tiger front door.  ·  `library/Tiger/Application.php`
- **Tiger_Application_Bootstrap** `@api` — Tiger's base application bootstrap.  ·  `library/Tiger/Application/Bootstrap.php`
- **Tiger_Application_Resource_Modules** `@api` — module bootstrapping, with an activation gate.  ·  `library/Tiger/Application/Resource/Modules.php`
- **Tiger_Version** `@api` — Tiger platform version.  ·  `library/Tiger/Version.php`

### Authentication

- **Tiger_Auth_Totp** `@api` — RFC 6238 time-based one-time passwords (the "authenticator app" factor), dependency-free.  ·  `library/Tiger/Auth/Totp.php`
- **Tiger_Model_AuthChallenge** `@api` — AuthChallenge — transient, single-use auth proofs (OTP codes, reset/verify/magic tokens).  ·  `library/Tiger/Model/AuthChallenge.php`
- **Tiger_Model_Login** `@api` — Login — the append-only authentication audit log (see migration 0011).  ·  `library/Tiger/Model/Login.php`
- **Tiger_Model_PasswordHistory** `@api` — PasswordHistory — retained old password hashes (see migration 0012).  ·  `library/Tiger/Model/PasswordHistory.php`
- **Tiger_Model_UserCredential** `@api` — UserCredential — durable authentication factors (1-to-many with user).  ·  `library/Tiger/Model/UserCredential.php`
- **Tiger_Policy_Password** `@api` — the configurable password policy.  ·  `library/Tiger/Policy/Password.php`
- **Tiger_Service_Authentication** `@api` — the AUTHENTICATION kernel service.  ·  `library/Tiger/Service/Authentication.php`
- **Tiger_Service_Token** `@api` — a user manages their own personal access tokens (stateless `/api` credentials).  ·  `library/Tiger/Service/Token.php`
- **Tiger_Validate_Password** `@api` — a form validator that runs the platform password policy.  ·  `library/Tiger/Validate/Password.php`

### Authorization (ACL)

- **Tiger_Acl_Acl** `@api` — the AUTHORIZATION engine (what may you do).  ·  `library/Tiger/Acl/Acl.php`
- **Tiger_Model_AclResource** `@api` — AclResource — runtime resource rows (DB layer).  ·  `library/Tiger/Model/AclResource.php`
- **Tiger_Model_AclRole** `@api` — AclRole — runtime role rows (the DB layer of the role graph).  ·  `library/Tiger/Model/AclRole.php`
- **Tiger_Model_AclRule** `@api` — AclRule — runtime allow/deny rules (DB layer, loaded LAST so DB wins).  ·  `library/Tiger/Model/AclRule.php`

### Identity & tenancy

- **Tiger_Account_Nav** `@api` — the "My Account" sidebar's nav registry (the module hook for USER screens).  ·  `library/Tiger/Account/Nav.php`
- **Tiger_Model_Address** `@api` — Address — a postal location, owner-agnostic.  ·  `library/Tiger/Model/Address.php`
- **Tiger_Model_Contact** `@api` — Contact — a point of contact (phone / email / other), owner-agnostic.  ·  `library/Tiger/Model/Contact.php`
- **Tiger_Model_Org** `@api` — Org — the TENANT.  ·  `library/Tiger/Model/Org.php`
- **Tiger_Model_OrgAddress** `@api` — OrgAddress — the org ↔ address link.  ·  `library/Tiger/Model/OrgAddress.php`
- **Tiger_Model_OrgContact** `@api` — OrgContact — the org ↔ contact link.  ·  `library/Tiger/Model/OrgContact.php`
- **Tiger_Model_OrgUser** `@api` — OrgUser — MEMBERSHIP.  ·  `library/Tiger/Model/OrgUser.php`
- **Tiger_Model_User** `@api` — User — a person / identity.  ·  `library/Tiger/Model/User.php`
- **Tiger_Model_UserAddress** `@api` — UserAddress — the user ↔ address link.  ·  `library/Tiger/Model/UserAddress.php`
- **Tiger_Model_UserContact** `@api` — UserContact — the user ↔ contact link.  ·  `library/Tiger/Model/UserContact.php`
- **Tiger_Profile_Tabs** `@api` — the extensible tab registry behind the self-service profile surfaces.  ·  `library/Tiger/Profile/Tabs.php`
- **Tiger_Profile_Types** `@api` — the configurable contact/address type lists for the profile tabs.  ·  `library/Tiger/Profile/Types.php`

### Crypto & secrets

- **Tiger_Crypto** `@api` — authenticated symmetric encryption for reversible secrets at rest.  ·  `library/Tiger/Crypto.php`
- **Tiger_Crypto_Signature** `@api` — detached Ed25519 signatures for artifact + message integrity.  ·  `library/Tiger/Crypto/Signature.php`
- **Tiger_Security** `@api` — the application PEPPER (a keyed secret mixed into hashes).  ·  `library/Tiger/Security.php`

### Web services (/api)

- **Tiger_Ajax_ServiceFactory** `@api` — the single-gateway /api dispatcher.  ·  `library/Tiger/Ajax/ServiceFactory.php`
- **Tiger_Model_MessageObject** `@api` — one feedback message in the API response envelope.  ·  `library/Tiger/Model/MessageObject.php`
- **Tiger_Model_ResponseObject** `@api` — the standard API response envelope.  ·  `library/Tiger/Model/ResponseObject.php`
- **Tiger_Service_Service** `@api` — abstract base for every /api service.  ·  `library/Tiger/Service/Service.php`
- **Tiger_Service_Validate** `@api` — convenience validation, over /api.  ·  `library/Tiger/Service/Validate.php`

### API discovery (OpenAPI)

- **Tiger_OpenApi_Generator** `@api` — an OpenAPI 3 document from the `/api` service surface.  ·  `library/Tiger/OpenApi/Generator.php`

### CMS / content

- **Tiger_Cms_Renderer** `@api` — turn CMS page/layout/partial content into HTML.  ·  `library/Tiger/Cms/Renderer.php`
- **Tiger_Model_Page** `@api` — Page — the CMS content store (see migration 0014).  ·  `library/Tiger/Model/Page.php`
- **Tiger_Model_PageRedirect** `@api` — PageRedirect — slug-change redirects (see migration 0016).  ·  `library/Tiger/Model/PageRedirect.php`
- **Tiger_Model_PageVersion** `@api` — PageVersion — append-only page history (see migration 0015).  ·  `library/Tiger/Model/PageVersion.php`

### Media

- **Tiger_Media_Image** `@api` — GD-backed image variant generation (thumbnails / sized copies).  ·  `library/Tiger/Media/Image.php`
- **Tiger_Media_Manifest** `@api` — discover the STATIC media that ACTIVE modules ship.  ·  `library/Tiger/Media/Manifest.php`
- **Tiger_Media_Migrator** `@api` — relocate stored media from one disk (storage adapter) to another.  ·  `library/Tiger/Media/Migrator.php`
- **Tiger_Media_Scan** `@api` — the upload scanning orchestrator.  ·  `library/Tiger/Media/Scan.php`
- **Tiger_Media_Scanner_ClamAv** `@api` — virus scan via ClamAV.  ·  `library/Tiger/Media/Scanner/ClamAv.php`
- **Tiger_Media_Scanner_Interface** `@api` — a pluggable content scanner for uploads.  ·  `library/Tiger/Media/Scanner/Interface.php`
- **Tiger_Media_Scanner_Rekognition** `@api` — AI content moderation via AWS Rekognition.  ·  `library/Tiger/Media/Scanner/Rekognition.php`
- **Tiger_Media_Storage** `@api` — resolves a configured storage disk to its adapter.  ·  `library/Tiger/Media/Storage.php`
- **Tiger_Media_Storage_Azure** `@api` — Azure Blob Storage media storage.  ·  `library/Tiger/Media/Storage/Azure.php`
- **Tiger_Media_Storage_Filesystem** `@api` — local-disk media storage.  ·  `library/Tiger/Media/Storage/Filesystem.php`
- **Tiger_Media_Storage_Gcs** `@api` — Google Cloud Storage media storage.  ·  `library/Tiger/Media/Storage/Gcs.php`
- **Tiger_Media_Storage_Interface** `@api` — the pluggable storage backend for media bytes.  ·  `library/Tiger/Media/Storage/Interface.php`
- **Tiger_Media_Storage_S3** `@api` — Amazon S3 (and S3-compatible) media storage.  ·  `library/Tiger/Media/Storage/S3.php`
- **Tiger_Model_Media** `@api` — Media — the file store's metadata (see migration 0018 + MEDIA.md).  ·  `library/Tiger/Model/Media.php`

### Theming

- **Tiger_Theme** `@api` — read helpers for the ACTIVE theme's on-disk resources.  ·  `library/Tiger/Theme.php`
- **Tiger_Theme_Menus** `@api` — a theme's declared public menus, read from `configs/menus.ini`.  ·  `library/Tiger/Theme/Menus.php`

### Menus

- **Tiger_Menu** `@api` — the render/read facade for custom menus (Tiger_Model_Menu is the store).  ·  `library/Tiger/Menu.php`

### Site search

- **Tiger_Search** `@api` — the pluggable site-search registry.  ·  `library/Tiger/Search.php`

### Custom fields

- **Tiger_Fields** `@api` — the custom-fields registry: declarative field groups that attach to CMS content and store in `page.meta`, versioned and org-cascaded for free (the same seam as `meta.seo.*`).  ·  `library/Tiger/Fields.php`

### Modules, marketplace & updates

- **Tiger_Generator_Module** `@api` — scaffolds a new application module.  ·  `library/Tiger/Generator/Module.php`
- **Tiger_License_Authority** `@api` — the client for a license authority's DOWNLOAD endpoint.  ·  `library/Tiger/License/Authority.php`
- **Tiger_License_Checker** `@api` — the client half of licensing: hold an install's keys, verify them against whichever authority a licensed module declares, and gate auto-update.  ·  `library/Tiger/License/Checker.php`
- **Tiger_License_Store** `@api` — where an install keeps the licenses it holds (one record per licensed module).  ·  `library/Tiger/License/Store.php`
- **Tiger_License_Store_Option** `@api` — the default license store, backed by the lazy `option` tier.  ·  `library/Tiger/License/Store/Option.php`
- **Tiger_License_Vendor** `@api` — CONNECT + pin a paid module's vendor (its `[owner]/TigerVendor` repo).  ·  `library/Tiger/License/Vendor.php`
- **Tiger_Model_Module** `@api` — the module lifecycle registry (see migration 0023).  ·  `library/Tiger/Model/Module.php`
- **Tiger_Model_UpdateHistory** `@api` — the durable log of one-click update runs (table `update_history`).  ·  `library/Tiger/Model/UpdateHistory.php`
- **Tiger_Module_Compat** `@api` — advisory "which Tiger versions was this module tested for?" metadata.  ·  `library/Tiger/Module/Compat.php`
- **Tiger_Module_Dependency** `@api` — lightweight, lazy inter-module dependency alerts.  ·  `library/Tiger/Module/Dependency.php`
- **Tiger_Module_Discovery** `@api` — find the modules present on disk (active or not).  ·  `library/Tiger/Module/Discovery.php`
- **Tiger_Module_Github** `@api` — read public GitHub repos over cURL (no auth, public only).  ·  `library/Tiger/Module/Github.php`
- **Tiger_Module_Installer** `@api` — install / update / remove modules from public GitHub repos.  ·  `library/Tiger/Module/Installer.php`
- **Tiger_Module_Pricing** `@api` — the manifest `pricing` block, normalized.  ·  `library/Tiger/Module/Pricing.php`
- **Tiger_Module_Registry** `@api` — the client for the module catalog, now **multi-source**.  ·  `library/Tiger/Module/Registry.php`
- **Tiger_Module_Source** `@api` — one catalog feed the Module Manager reads.  ·  `library/Tiger/Module/Source.php`
- **Tiger_Update_Checker** `@api` — "what has an update?" for the WordPress-simple Updates screen.  ·  `library/Tiger/Update/Checker.php`
- **Tiger_Update_Composer** `@api` — run `composer update <package>` IN-PROCESS, for hosts where Composer genuinely runs (a binary + proc_open/exec not disabled + a writable vendor/ — see Tiger_Vendor_Environment).  ·  `library/Tiger/Update/Composer.php`
- **Tiger_Update_Core** `@api` — no-shell TigerCore self-update via a pre-resolved vendored release ZIP.  ·  `library/Tiger/Update/Core.php`
- **Tiger_Vendor** `@api` — provisions a third-party PHP library and makes it autoloadable, on any host.  ·  `library/Tiger/Vendor.php`
- **Tiger_Vendor_Environment** `@api` — reads the host's capability for provisioning third-party libraries.  ·  `library/Tiger/Vendor/Environment.php`

### Install & first-run

- **Tiger_Install** `@api` — first-run bootstrap helpers.  ·  `library/Tiger/Install.php`

### Config, i18n & options

- **Tiger_I18n_Country** `@api` — the country list, localized + biased-sorted.  ·  `library/Tiger/I18n/Country.php`
- **Tiger_I18n_Timezone** `@api` — the IANA timezone list, enriched for a searchable picker.  ·  `library/Tiger/I18n/Timezone.php`
- **Tiger_Model_Config** `@api` — Config — the runtime config override layer (see migration 0009).  ·  `library/Tiger/Model/Config.php`
- **Tiger_Model_Option** `@api` — Option — the LAZY, on-demand scoped key/value store (see migration 0031).  ·  `library/Tiger/Model/Option.php`
- **Tiger_Model_Translation** `@api` — Translation — live translation overrides (the DB tier of i18n; see migration 0013).  ·  `library/Tiger/Model/Translation.php`

### Mail

- **Tiger_Mail** `@api` — a thin, fluent wrapper over Zend_Mail.  ·  `library/Tiger/Mail.php`

### Location

- **Tiger_Location** `@api` — the Location Service facade.  ·  `library/Tiger/Location.php`
- **Tiger_Location_Adapter_Abstract** `@api` — base for Location adapters.  ·  `library/Tiger/Location/Adapter/Abstract.php`
- **Tiger_Location_Adapter_Aws** `@api` — AWS Location Service (Places) adapter.  ·  `library/Tiger/Location/Adapter/Aws.php`
- **Tiger_Location_Adapter_Interface** `@api` — the capability contract every Location provider implements.  ·  `library/Tiger/Location/Adapter/Interface.php`
- **Tiger_Location_Adapter_IpApi** `@api` — approximate location for an IP address (CAP_IP only).  ·  `library/Tiger/Location/Adapter/IpApi.php`
- **Tiger_Location_Adapter_Nominatim** `@api` — OpenStreetMap geocoding (the free/zero-config default).  ·  `library/Tiger/Location/Adapter/Nominatim.php`
- **Tiger_Location_Exception** `@api` — Location service / adapter error (unsupported capability, misconfiguration, provider failure).  ·  `library/Tiger/Location/Exception.php`
- **Tiger_Location_Place** `@api` — the ONE normalized payload every Location adapter returns.  ·  `library/Tiger/Location/Place.php`
- **Tiger_Service_Location** `@api` — the Location Service over /api.  ·  `library/Tiger/Service/Location.php`

### Logging

- **Tiger_Log** `@api` — the platform logging facade.  ·  `library/Tiger/Log.php`

### Sessions

- **Tiger_Session_SaveHandler_DbTable** `@api` — DB-backed PHP session handler.  ·  `library/Tiger/Session/SaveHandler/DbTable.php`

### AI agent

- **Tiger_Agent** `@api` — the TigerAgent facade (config + availability + capability).  ·  `library/Tiger/Agent.php`
- **Tiger_Agent_Contract** `@api` — the request/response contract between the app and the model.  ·  `library/Tiger/Agent/Contract.php`
- **Tiger_Agent_Forge** `@api` — the permission-gated hands of the agent (TIGERAGENT.md §0, §2a, §3).  ·  `library/Tiger/Agent/Forge.php`
- **Tiger_Agent_Loop** `@api` — run an agent turn as a multi-step ReAct loop (TIGERAGENT.md §5).  ·  `library/Tiger/Agent/Loop.php`
- **Tiger_Agent_Provider_Adapter** `@api` — the contract every AI provider adapter implements.  ·  `library/Tiger/Agent/Provider/Adapter.php`
- **Tiger_Agent_Provider_Anthropic** `@api` — the Anthropic Messages API adapter (the reference).  ·  `library/Tiger/Agent/Provider/Anthropic.php`
- **Tiger_Agent_Provider_DeepSeek** `@api` — DeepSeek (OpenAI-compatible; very low cost).  ·  `library/Tiger/Agent/Provider/DeepSeek.php`
- **Tiger_Agent_Provider_Factory** `@api` — resolve a provider adapter (and its default model) by key.  ·  `library/Tiger/Agent/Provider/Factory.php`
- **Tiger_Agent_Provider_Gemini** `@api` — Google Gemini via the Generative Language API (Google AI Studio key).  ·  `library/Tiger/Agent/Provider/Gemini.php`
- **Tiger_Agent_Provider_Grok** `@api` — xAI Grok (OpenAI-compatible).  ·  `library/Tiger/Agent/Provider/Grok.php`
- **Tiger_Agent_Provider_Groq** `@api` — Groq's fast open-model inference (OpenAI-compatible; free tier).  ·  `library/Tiger/Agent/Provider/Groq.php`
- **Tiger_Agent_Provider_Mistral** `@api` — Mistral La Plateforme (OpenAI-compatible; free tier).  ·  `library/Tiger/Agent/Provider/Mistral.php`
- **Tiger_Agent_Provider_OpenAi** `@api` — OpenAI (GPT) via the chat/completions API.  ·  `library/Tiger/Agent/Provider/OpenAi.php`
- **Tiger_Agent_Provider_OpenAiCompatible** `@api` — the base adapter for every provider that speaks the OpenAI `/chat/completions` wire format.  ·  `library/Tiger/Agent/Provider/OpenAiCompatible.php`
- **Tiger_Agent_Provider_OpenRouter** `@api` — OpenRouter: one key, many models (incl.  ·  `library/Tiger/Agent/Provider/OpenRouter.php`
- **Tiger_Agent_Scout** `@api` — the agent's EYES: the read twin of the Forge (TIGERAGENT.md §2b).  ·  `library/Tiger/Agent/Scout.php`
- **Tiger_Agent_Tools** `@api` — build the model's tool catalog + system prompt from the LIVE, role- filtered /api surface (TIGERAGENT.md §2, §5a).  ·  `library/Tiger/Agent/Tools.php`

### Agent skills

- **Tiger_Skill_Index** `@api` — the internal, searchable skill catalog built by running the source adapters.  ·  `library/Tiger/Skill/Index.php`
- **Tiger_Skill_Source** `@api` — a browse adapter for one supported skill repo (scan + normalize, NOT endorse).  ·  `library/Tiger/Skill/Source.php`
- **Tiger_Skill_Source_SkillsDir** `@api` — the adapter for the common "collection" layout: a repo whose skills live as `<base>/<name>/SKILL.md` folders (e.g.  ·  `library/Tiger/Skill/Source/SkillsDir.php`
- **Tiger_Skill_Source_Url** `@api` — the "paste a GitHub URL" adapter.  ·  `library/Tiger/Skill/Source/Url.php`

### Scheduling

- **Tiger_Model_ScheduleRun** `@api` — ScheduleRun — one execution record of a Tiger_Schedule job (the run log + the "last run" state).  ·  `library/Tiger/Model/ScheduleRun.php`
- **Tiger_Schedule** `@api` — a shared-host-friendly job scheduler.  ·  `library/Tiger/Schedule.php`

### Backup

- **Tiger_Backup** `@api` — create and restore site backups (a downloadable/portable zip).  ·  `library/Tiger/Backup.php`
- **Tiger_Backup_Archive** `@api` — a tiny zip read/write shim that works with or without ext-zip.  ·  `library/Tiger/Backup/Archive.php`
- **Tiger_Backup_Database** `@api` — a portable, shell-free SQL dump + restore over the app's DB adapter.  ·  `library/Tiger/Backup/Database.php`
- **Tiger_Model_Backup** `@api` — the catalog of backup archives (metadata; the bytes live on a disk).  ·  `library/Tiger/Model/Backup.php`

### Code area

- **Tiger_Code_Modules** `@api` — file-based code snippets shipped by installed `code` modules.  ·  `library/Tiger/Code/Modules.php`
- **Tiger_Code_Runtime** `@api` — compile-and-run for Tiger Code's PHP tier.  ·  `library/Tiger/Code/Runtime.php`
- **Tiger_Model_Code** `@api` — the Tiger Code store (executable PHP + client CSS/JS/HTML).  ·  `library/Tiger/Model/Code.php`
- **Tiger_Model_CodeVersion** `@api` — immutable snapshots of `code` rows (see Tiger_Model_Code).  ·  `library/Tiger/Model/CodeVersion.php`

### Audience, analytics & consent

- **Tiger_Audience** `@api` — the registry of email/marketing AUDIENCE segments a module can offer.  ·  `library/Tiger/Audience.php`
- **Tiger_Consent** `@api` — the GDPR cookie-consent gate.  ·  `library/Tiger/Consent.php`
- **Tiger_Google_Analytics** `@api` — a tiny, dependency-free client for the Google Analytics 4 **reporting** side (pulling stats back for the in-app dashboard).  ·  `library/Tiger/Google/Analytics.php`
- **Tiger_Tracking** `@api` — a process-wide registry of the tracking/analytics scripts an install runs.  ·  `library/Tiger/Tracking.php`

### SEO & accessibility

- **Tiger_Ally** `@api` — a nominal accessibility (ADA / WCAG-A) inspector for HTML.  ·  `library/Tiger/Ally.php`
- **Tiger_Sitemap** `@api` — the registry of a site's PUBLIC (guest-reachable) URLs.  ·  `library/Tiger/Sitemap.php`

### Admin shell & dashboard

- **Tiger_Admin_Header** `@api` — the registry for action slots in the admin's TOP HEADER BAR.  ·  `library/Tiger/Admin/Header.php`
- **Tiger_Admin_Nav** `@api` — the admin sidebar's TOP-LEVEL nav registry (the module hook).  ·  `library/Tiger/Admin/Nav.php`
- **Tiger_Admin_Settings** `@api` — the admin "Settings" registry (the module hook).  ·  `library/Tiger/Admin/Settings.php`
- **Tiger_Admin_UserMenu** `@api` — the registry for items in the header's USER dropdown (the avatar menu).  ·  `library/Tiger/Admin/UserMenu.php`
- **Tiger_Controller_Account_Action** `@api` — the base for every "My Account" screen (/account surface).  ·  `library/Tiger/Controller/Account/Action.php`
- **Tiger_Controller_Admin_Action** `@api` — the base for every ADMIN-shell controller.  ·  `library/Tiger/Controller/Admin/Action.php`
- **Tiger_Dashboard** `@api` — the admin dashboard widget registry (the module hook).  ·  `library/Tiger/Dashboard.php`

### Forms & views

- **Tiger_Form** `@api` — base class for every Tiger form.  ·  `library/Tiger/Form.php`
- **Tiger_Form_Element_Hash** `@api` — a CSRF token that lives for its full TIMEOUT, not a single request.  ·  `library/Tiger/Form/Element/Hash.php`
- **Tiger_Form_Element_Recaptcha** `@api` — a Google reCAPTCHA field for Tiger_Form.  ·  `library/Tiger/Form/Element/Recaptcha.php`
- **Tiger_Recaptcha** `@api` — the Google reCAPTCHA integration hub (config + server verify).  ·  `library/Tiger/Recaptcha.php`
- **Tiger_Validate_Recaptcha** `@api` — server-side validation of a Google reCAPTCHA response.  ·  `library/Tiger/Validate/Recaptcha.php`
- **Tiger_View_Helper_Asset** `@api` — cache-busting asset URLs.  ·  `library/Tiger/View/Helper/Asset.php`
- **Tiger_View_Helper_CodeInject** `@api` — emit Tiger Code's client tier into the page.  ·  `library/Tiger/View/Helper/CodeInject.php`
- **Tiger_View_Helper_FormRecaptcha** `@api` — renders the Google reCAPTCHA widget.  ·  `library/Tiger/View/Helper/FormRecaptcha.php`
- **Tiger_View_Helper_MediaField** `@api` — a form field that picks media via TigerMediaPicker.  ·  `library/Tiger/View/Helper/MediaField.php`
- **Tiger_View_Helper_Menu** `@api` — render a custom menu in a view: `<?= $this->menu('primary') ?>`.  ·  `library/Tiger/View/Helper/Menu.php`
- **Tiger_View_Helper_PageField** `@api` — read a custom field value (Tiger_Fields) from a page on the front end.  ·  `library/Tiger/View/Helper/PageField.php`
- **Tiger_View_Helper_PageScript** `@api` — register a page-specific JS file from a view WITHOUT a `<script>` tag.  ·  `library/Tiger/View/Helper/PageScript.php`
- **Tiger_View_Helper_PageStyle** `@api` — register a page-specific stylesheet from a view WITHOUT a `<style>` tag.  ·  `library/Tiger/View/Helper/PageStyle.php`

### Routing & controllers

- **Tiger_Controller_Action** `@api` — project base controller for CONVENIENCES ONLY.  ·  `library/Tiger/Controller/Action.php`
- **Tiger_Controller_Plugin_Authorization** `@api` — the AUTHORIZATION gate.  ·  `library/Tiger/Controller/Plugin/Authorization.php`
- **Tiger_Controller_Plugin_LocalePrefix** `@api` — semantic /xx/ locale URLs + language resolution.  ·  `library/Tiger/Controller/Plugin/LocalePrefix.php`
- **Tiger_Controller_Plugin_PageDispatch** `@api` — let CMS content own the site's public URLs.  ·  `library/Tiger/Controller/Plugin/PageDispatch.php`
- **Tiger_Controller_Plugin_RouteOverride** `@api` — apply declared pretty-route overrides.  ·  `library/Tiger/Controller/Plugin/RouteOverride.php`
- **Tiger_Controller_Plugin_ScheduleTick** `@internal` — the WordPress-style pseudo-cron for Tiger_Schedule.  ·  `library/Tiger/Controller/Plugin/ScheduleTick.php`
- **Tiger_Controller_Plugin_ThemeContent** `@api` — serve a theme's BUNDLED STATIC pages.  ·  `library/Tiger/Controller/Plugin/ThemeContent.php`
- **Tiger_Routing_Overrides** `@api` — the pretty-route registry (module hook + admin override tier).  ·  `library/Tiger/Routing/Overrides.php`

### Data layer (base)

- **Tiger_Db_Migrator** `@api` — a tiny, dependency-free schema migration runner.  ·  `library/Tiger/Db/Migrator.php`
- **Tiger_Model_AgentConversation** `@api` — a TigerAgent chat thread (see migration 0034).  ·  `library/Tiger/Model/AgentConversation.php`
- **Tiger_Model_AgentMessage** `@api` — one message in an agent conversation (see migration 0035).  ·  `library/Tiger/Model/AgentMessage.php`
- **Tiger_Model_AgentRun** `@api` — one turn's execution + control record (see migration 0036).  ·  `library/Tiger/Model/AgentRun.php`
- **Tiger_Model_Menu** `@api` — Menu — custom navigation menus (see migration 0017).  ·  `library/Tiger/Model/Menu.php`
- **Tiger_Model_Session** `@api` — Session — gateway for the DB session store (see migration 0010).  ·  `library/Tiger/Model/Session.php`
- **Tiger_Model_Table** `@api` — Base table-gateway for Tiger models.  ·  `library/Tiger/Model/Table.php`
- **Tiger_Uuid** `@api` — UUID generation for Tiger primary keys.  ·  `library/Tiger/Uuid.php`

## Modules (`modules/*` — activatable features)

- **Access** (`access`, plugin)  ·  services: Org, User  ·  `modules/access`
- **Agent** (`agent`, app)  ·  services: Agent, Settings  ·  `modules/agent`
- **Ally** (`ally`, plugin)  ·  services: Scan  ·  `modules/ally`
- **Analytics** (`analytics`, app)  ·  services: Analytics, Reports  ·  `modules/analytics`
- **Backup** (`backup`, app)  ·  services: Backup  ·  `modules/backup`
- **Blog** (`blog`, app)  ·  services: Post, Taxonomy  ·  `modules/blog`
- **CMS** (`cms`, app)  ·  services: Menu, Page, Settings  ·  `modules/cms`
- **Code** (`code`, developer)  ·  services: Code  ·  `modules/code`
- **Identity** (`identity`, plugin)  ·  services: Identity  ·  `modules/identity`
- **Media** (`media`, plugin)  ·  services: Media, Settings  ·  `modules/media`
- **Profile** (`profile`, plugin)  ·  services: Address, Avatar, Base, Contact, Org, OrgAddress, OrgContact, OrgLogo, Security, User  ·  `modules/profile`
- **Register** (`register`, plugin)  ·  services: Registration, Status  ·  `modules/register`
- **Schedule** (`schedule`, developer)  ·  services: Schedule  ·  `modules/schedule`
- **Search** (`search`, plugin)  ·  services: Search  ·  `modules/search`
- **SEO** (`seo`, app)  ·  services: Head, Schema  ·  `modules/seo`
- **Signup** (`signup`, plugin)  ·  services: Signup  ·  `modules/signup`
- **System** (`system`, plugin)  ·  services: Acl, Dashboard, Logs, Modules, Nav, Settings, Updates  ·  `modules/system`

