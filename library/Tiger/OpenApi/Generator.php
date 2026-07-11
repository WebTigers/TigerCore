<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_OpenApi_Generator — an OpenAPI 3 document from the `/api` service surface.
 *
 * The TIGER gateway already knows the whole API — every `module`/`service`/`method`, each service's
 * public methods, and (via the ACL) who may call them. So discovery is "reflect what the gateway
 * knows and emit OpenAPI" — the same "describe from the code" move as the docs reference generator,
 * pointed at services + Forms.
 *
 * Each service method becomes one operation at `POST /api/{module}/{service}/{method}` (the endpoint
 * is verb-agnostic — see WEBSERVICES.md §9). The request body comes from the method's **Form** (its
 * declared `elements()`), the response is the shared TIGER envelope, and operations are tagged by
 * module.
 *
 * Phase 1: operations + envelope + form→schema + tags. Serving (a `describe` route + Swagger UI),
 * role-filtering (discovery respects the ACL), and richer `data` typing are later phases — §9.
 *
 * @api
 */
class Tiger_OpenApi_Generator
{
    const OPENAPI = '3.0.3';

    /** @var array OpenAPI `info` block. */
    protected $_info;

    /**
     * @param array $info OpenAPI `info` overrides (title, version, description)
     */
    public function __construct(array $info = [])
    {
        $this->_info = $info + [
            'title'       => 'Tiger API',
            'version'     => '1.0.0',
            'description' => 'The TIGER message API — one endpoint (`/api`), verb-agnostic. Every '
                           . 'operation is also callable as `POST /api` with `{module, service, method, …}`.',
        ];
    }

    /**
     * Discover `*_Service_*` class names under a set of module `services/` dirs (no autoload needed —
     * a light source scan; `generate()` then reflects the ones that are loadable).
     *
     * @param  array $serviceDirs absolute paths to `services/` directories
     * @return array              the discovered service class names
     */
    public function discover(array $serviceDirs)
    {
        $classes = [];
        foreach ($serviceDirs as $dir) {
            foreach ((glob(rtrim($dir, '/') . '/*.php') ?: []) as $file) {
                $src = (string) @file_get_contents($file);
                if (preg_match('/\bclass\s+([A-Za-z0-9_]+_Service_[A-Za-z0-9_]+)\b/', $src, $m)) {
                    $classes[] = $m[1];
                }
            }
        }
        return array_values(array_unique($classes));
    }

    /**
     * The `services/` dir of every discovered module (app + first-party core) — a convenience so a
     * caller can do `generate(discover(moduleServiceDirs()))` without re-deriving module paths.
     *
     * @return array absolute paths to each module's `services/` directory
     */
    public function moduleServiceDirs()
    {
        $dirs = [];
        foreach (Tiger_Module_Discovery::all() as $slug => $m) {
            $base = ($m['area'] === 'app' && defined('APPLICATION_PATH')) ? APPLICATION_PATH
                  : (defined('TIGER_CORE_PATH') ? TIGER_CORE_PATH : null);
            if ($base !== null) {
                $dirs[] = $base . '/modules/' . $slug . '/services';
            }
        }
        return $dirs;
    }

    /**
     * Build the OpenAPI 3 document (as an array) for a set of service classes.
     *
     * @param  array $serviceClasses fully-qualified `Module_Service_X` class names
     * @return array                 the OpenAPI 3 document
     */
    public function generate(array $serviceClasses)
    {
        $paths = [];
        $tags  = [];
        foreach ($serviceClasses as $class) {
            if (!$this->_isService($class)) {
                continue;
            }
            [$module, $service] = $this->_moduleService($class);
            $tags[$module] = true;
            foreach ($this->_operations($class) as $method) {
                $path = '/api/' . $module . '/' . $service . '/' . $method->getName();
                $paths[$path]['post'] = $this->_operation($module, $service, $method);
            }
        }
        ksort($paths);

        return [
            'openapi'    => self::OPENAPI,
            'info'       => $this->_info,
            'servers'    => [['url' => '/']],
            'tags'       => array_map(static fn($t) => ['name' => $t], array_keys($tags)),
            'paths'      => $paths,
            'components' => ['schemas' => ['TigerResponse' => $this->_envelopeSchema()]],
        ];
    }

    // ============================================================================= operations

    /** One OpenAPI operation from a reflected service method. */
    protected function _operation($module, $service, ReflectionMethod $method)
    {
        $doc = $this->_docblock($method);
        $op  = [
            'operationId' => $module . '.' . $service . '.' . $method->getName(),
            'tags'        => [$module],
            'summary'     => $doc['summary'] !== '' ? $doc['summary'] : ($service . ' · ' . $method->getName()),
            'description' => trim($doc['description'] . "\n\nAlso callable as `POST /api` with "
                           . "`{module: \"$module\", service: \"$service\", method: \"{$method->getName()}\", …}`."),
            'responses'   => [
                '200' => [
                    'description' => 'TIGER response envelope',
                    'content'     => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/TigerResponse']]],
                ],
            ],
        ];
        $schema = $this->_requestSchema($doc);
        if ($schema !== null) {
            $op['requestBody'] = ['content' => ['application/x-www-form-urlencoded' => ['schema' => $schema]]];
        }
        return $op;
    }

    /** Public, own, non-`_` instance methods (skip the base's helpers + `__construct`). */
    protected function _operations($class)
    {
        $out = [];
        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->isStatic() || $m->getName() === '__construct' || strpos($m->getName(), '_') === 0) {
                continue;
            }
            if ($m->getDeclaringClass()->getName() === 'Tiger_Service_Service') {
                continue;   // inherited base helpers aren't operations
            }
            $out[] = $m;
        }
        usort($out, static fn($a, $b) => strcmp($a->getName(), $b->getName()));
        return $out;
    }

    // ========================================================================= request schema

    /** Request body schema — from the method's `@apiRequest <FormClass>`, else a generic object. */
    protected function _requestSchema(array $doc)
    {
        $formClass = $doc['apiRequest'];
        if ($formClass !== '' && class_exists($formClass)) {
            try {
                $form = new $formClass();
                if (method_exists($form, 'getElements')) {
                    return $this->_formSchema($form);
                }
            } catch (Throwable $e) {
                // fall through to the generic body
            }
        }
        return ['type' => 'object', 'additionalProperties' => true, 'description' => 'Service parameters.'];
    }

    /** A Zend/Tiger form's declared elements → an OpenAPI object schema. */
    protected function _formSchema($form)
    {
        $props    = [];
        $required = [];
        foreach ($form->getElements() as $name => $el) {
            if ($name === '_csrf' || $name === 'csrf') {
                continue;
            }
            $props[$name] = $this->_elementSchema($el);
            if (method_exists($el, 'isRequired') && $el->isRequired()) {
                $required[] = $name;
            }
        }
        $schema = ['type' => 'object', 'properties' => $props];
        if ($required) {
            $schema['required'] = array_values(array_unique($required));
        }
        return $schema;
    }

    /** Map one form element to an OpenAPI property (type by element class; label = description). */
    protected function _elementSchema($el)
    {
        $t = strtolower(get_class($el));
        if (strpos($t, 'checkbox') !== false)                                      { $s = ['type' => 'boolean']; }
        elseif (strpos($t, 'email') !== false)                                     { $s = ['type' => 'string', 'format' => 'email']; }
        elseif (strpos($t, 'password') !== false)                                  { $s = ['type' => 'string', 'format' => 'password']; }
        elseif (strpos($t, 'number') !== false || strpos($t, 'spinner') !== false) { $s = ['type' => 'number']; }
        else                                                                       { $s = ['type' => 'string']; }
        if (method_exists($el, 'getLabel') && ($label = $el->getLabel()) !== null && $label !== '') {
            $s['description'] = (string) $label;
        }
        return $s;
    }

    // ======================================================================== response envelope

    protected function _envelopeSchema()
    {
        return [
            'type'        => 'object',
            'description' => 'The standard TIGER response envelope.',
            'properties'  => [
                'result'   => ['type' => 'integer', 'enum' => [0, 1], 'description' => '1 = success, 0 = failure'],
                'data'     => ['type' => 'object', 'nullable' => true, 'description' => 'Service payload (shape varies by operation).'],
                'redirect' => ['type' => 'string', 'nullable' => true, 'description' => 'Optional client-side redirect.'],
                'form'     => ['type' => 'object', 'nullable' => true, 'description' => 'Keyed field errors (Zend_Form).'],
                'messages' => [
                    'type'  => 'array',
                    'items' => ['type' => 'object', 'properties' => [
                        'message' => ['type' => 'string'],
                        'class'   => ['type' => 'string', 'enum' => ['success', 'error', 'alert', 'info']],
                        'field'   => ['type' => 'string', 'nullable' => true],
                    ]],
                ],
            ],
            'required' => ['result'],
        ];
    }

    // ================================================================================== helpers

    protected function _isService($class)
    {
        return is_string($class) && class_exists($class) && is_subclass_of($class, 'Tiger_Service_Service');
    }

    /** `Module_Service_X` → [module-slug, service-slug]. */
    protected function _moduleService($class)
    {
        $parts = explode('_Service_', $class, 2);
        return [strtolower($parts[0]), strtolower(str_replace('_', '-', $parts[1] ?? ''))];
    }

    /** Parse a method docblock → ['summary','description','apiRequest']. */
    protected function _docblock(ReflectionMethod $method)
    {
        $lines = [];
        foreach (preg_split('/\r?\n/', (string) $method->getDocComment()) as $l) {
            $l = preg_replace('#^\s*/\*\*?#', '', $l);
            $l = preg_replace('#\*/\s*$#', '', $l);
            $l = preg_replace('/^\s*\*\s?/', '', $l);
            $lines[] = rtrim($l);
        }
        $i = 0; $n = count($lines);
        while ($i < $n && trim($lines[$i]) === '') { $i++; }
        $sum = [];
        for (; $i < $n; $i++) {
            if (trim($lines[$i]) === '' || preg_match('/^\s*@/', $lines[$i])) { break; }
            $sum[] = trim($lines[$i]);
        }
        $desc = [];
        for (; $i < $n; $i++) {
            if (preg_match('/^\s*@/', $lines[$i])) { break; }
            $desc[] = $lines[$i];
        }
        $apiRequest = '';
        for (; $i < $n; $i++) {
            if (preg_match('/^@apiRequest\s+(\S+)/', trim($lines[$i]), $m)) { $apiRequest = $m[1]; }
        }
        return ['summary' => implode(' ', $sum), 'description' => trim(implode("\n", $desc)), 'apiRequest' => $apiRequest];
    }
}
