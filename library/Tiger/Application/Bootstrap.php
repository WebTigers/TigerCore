<?php
/**
 * Tiger's base application bootstrap.
 *
 * An app's application/Bootstrap.php extends this to inherit module scanning,
 * default-namespace wiring, theme-as-path resolution, and the config cascade —
 * without copying any of it. Tiger-owned; extend behavior by adding _init*
 * methods in the app subclass, not by editing this file.
 *
 * @api
 */
class Tiger_Application_Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    /**
     * Wire ZF1 paths. The default (module-less) namespace is served from the
     * tiger-core package; app modules come from resources.frontController.
     * moduleDirectory (core.ini), and first-party core modules are registered
     * here too, so resources.modules can auto-bootstrap both.
     */
    protected function _initTigerPaths()
    {
        $this->bootstrap('frontController');
        $front = $this->getResource('frontController');

        // Default namespace = Core, shipped from the package:
        $front->setControllerDirectory(TIGER_CORE_PATH . '/core/controllers', 'default');

        // First-party core modules (if any ship in the package):
        if (is_dir(TIGER_CORE_PATH . '/modules')) {
            $front->addModuleDirectory(TIGER_CORE_PATH . '/modules');
        }
    }

    /**
     * Theme as a path (AskLevi-style, generalized). Active theme + skin resolve
     * from config now; per-org via the DB layer later. Layout comes from the
     * active theme; view script paths cascade Core -> theme -> app. No
     * inheritance — just paths.
     */
    protected function _initTheme()
    {
        $this->bootstrap('tigerPaths');

        $opts  = $this->getOption('tiger') ?: array();
        $theme = isset($opts['theme']) && $opts['theme'] !== '' ? $opts['theme'] : 'puma';
        $skin  = isset($opts['skin'])  ? $opts['skin']  : '';

        // Prefer an app-provided theme dir, else the package's:
        $themeDir = APPLICATION_PATH . '/themes/' . $theme;
        if (!is_dir($themeDir)) {
            $themeDir = TIGER_CORE_PATH . '/themes/' . $theme;
        }

        defined('THEME') || define('THEME', $theme);
        defined('SKIN')  || define('SKIN', $skin);

        // View script paths cascade (last added wins): Core -> theme -> app.
        $view = new Zend_View();
        $view->doctype('HTML5');
        $view->addScriptPath(TIGER_CORE_PATH . '/core/views/scripts');
        if (is_dir($themeDir . '/views/scripts')) {
            $view->addScriptPath($themeDir . '/views/scripts');
        }
        if (is_dir(APPLICATION_PATH . '/views/scripts')) {
            $view->addScriptPath(APPLICATION_PATH . '/views/scripts');
        }

        $view->theme       = $theme;
        $view->skin        = $skin;
        $view->themeAssets = '/_theme';

        Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->setView($view);

        // Layout from the active theme:
        if (is_dir($themeDir . '/layouts/scripts')) {
            $layout = Zend_Layout::startMvc(array(
                'layoutPath' => $themeDir . '/layouts/scripts',
                'layout'     => 'layout',
            ));
            $layout->setView($view);
        }

        Zend_Registry::set('Tiger_View', $view);
    }

    /**
     * Publish the effective config into the registry as 'Zend_Config'. Base is
     * the merged ini cascade (core <- application <- local). The per-org DB
     * override layer folds in here once the substrate (org + config table)
     * exists — à la AskLevi Core_Bootstrap::_initConfigs layer 3.
     */
    protected function _initConfigs()
    {
        $config = new Zend_Config($this->getOptions(), true);

        // TODO(substrate): merge DB config rows scoped 'global' + current org here.

        Zend_Registry::set('Zend_Config', $config);
        return $config;
    }
}
