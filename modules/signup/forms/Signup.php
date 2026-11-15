<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Signup_Form_Signup — the platform's reference form.
 *
 * This is the gold-standard example an AI agent (or a human) copies when building ANY Tiger
 * form: it exercises the full breadth of validation — required, length, regex, email,
 * InArray (selects), DB-uniqueness (Zend_Validate_Db_NoRecordExists on username + email),
 * and the platform password policy (Tiger_Validate_Password). Every field is validated the
 * same way twice: on blur via convenience validation (TigerValidateJS -> Tiger_Service_Validate
 * -> Tiger_Form::convenienceValidate, running exactly these validators one field at a time),
 * and again at submit via isValid(). Declare validators ONCE here; both paths use them.
 *
 * The view (views/scripts/index/index.phtml) owns all markup + layout; this class only
 * declares the elements + their rules.
 *
 * @api
 */
class Signup_Form_Signup extends Tiger_Form
{
    /** Session namespace holding the mode of the form this visitor was last shown. */
    const MODE_NAMESPACE = 'Signup_Form_Signup_mode';

    /**
     * Whether a submit of THIS form sets up a new org for the new user (true, the default) or adds
     * the new user to the org of the site the form is served on (false). Set in code by whoever builds
     * the form — `new Signup_Form_Signup(['setupOrg' => false])` — never from the request.
     *
     * @var bool
     */
    protected $_setupOrg = true;

    private static $_phoneTypes = [
        'mobile' => 'Mobile', 'work' => 'Work', 'home' => 'Home', 'main' => 'Main', 'fax' => 'Fax',
    ];

    /**
     * Choose the form's signup mode (Zend_Form calls this for the `setupOrg` constructor option,
     * before the elements are built).
     *
     * @param  bool $on true = the new user gets a new org; false = the new user joins this site's org
     * @return self
     */
    public function setSetupOrg($on): self
    {
        $this->_setupOrg = (bool) $on;
        return $this;
    }

    /**
     * Whether a submit of this form sets up a new org.
     *
     * @return bool
     */
    public function setupOrg(): bool
    {
        return $this->_setupOrg;
    }

    /**
     * Record this form's mode for the visitor it is being shown to, so the submit can't change it.
     *
     * Called by the controller that renders the form. The mode lives in the visitor's server-side
     * session next to the form's CSRF token, and Signup_Service_Signup::create reads it from there,
     * never from the posted payload. A join-mode form also records the org it joins: the org of the
     * site it is shown on, resolved now.
     *
     * @return array{setup_org:bool,org_id:string} the recorded mode
     */
    public function bind(): array
    {
        $mode = [
            'setup_org' => $this->_setupOrg,
            'org_id'    => $this->_setupOrg ? '' : (string) Tiger_Model_Org::siteOrgId(),
        ];
        // A setup-org form pins the default, so it can skip the write when the session isn't writable
        // (nothing bound already means "a new org"). A join form must pin its org — let that write fail
        // loudly rather than silently turn a store's customer signup into a new-org signup.
        if ($this->_setupOrg && !Zend_Session::isWritable()) {
            return $mode;
        }
        $ns = new Zend_Session_Namespace(self::MODE_NAMESPACE);
        $ns->mode = $mode;
        return $mode;
    }

    /**
     * The mode recorded by the last bind() in this visitor's session. Defaults to setting up a new
     * org when nothing valid was recorded, which is the behaviour every install had before the mode
     * existed and can never place anyone into an existing org.
     *
     * @return array{setup_org:bool,org_id:string}
     */
    public static function boundMode(): array
    {
        $mode = null;
        try {
            $ns   = new Zend_Session_Namespace(self::MODE_NAMESPACE);
            $mode = $ns->mode ?? null;
        } catch (Throwable $e) {
            $mode = null;
        }
        if (is_array($mode) && ($mode['setup_org'] ?? true) === false && (string) ($mode['org_id'] ?? '') !== '') {
            return ['setup_org' => false, 'org_id' => (string) $mode['org_id']];
        }
        return ['setup_org' => true, 'org_id' => ''];
    }

    protected function elements(): array
    {
        $control = ['class' => 'form-control'];
        $select  = ['class' => 'form-select'];

        // DB-uniqueness — the NoDbRecordExists validators, with human messages.
        $uniqueUsername = new Zend_Validate_Db_NoRecordExists(['table' => 'user', 'field' => 'username']);
        $uniqueUsername->setMessage('That username is already taken.', Zend_Validate_Db_NoRecordExists::ERROR_RECORD_FOUND);
        $uniqueEmail = new Zend_Validate_Db_NoRecordExists(['table' => 'user', 'field' => 'email']);
        $uniqueEmail->setMessage('An account with that email already exists.', Zend_Validate_Db_NoRecordExists::ERROR_RECORD_FOUND);

        $countryCodes = Tiger_I18n_Country::codes();   // full ISO-3166 alpha-2 set

        return [
            ['text', 'first_name', [
                'required' => true, 'filters' => ['StringTrim'],
                'validators' => [['StringLength', true, ['min' => 1, 'max' => 64]]],
                'attribs' => array_merge($control, ['id' => 'su-first', 'autocomplete' => 'given-name', 'placeholder' => 'Jane']),
            ]],
            ['text', 'last_name', [
                'required' => true, 'filters' => ['StringTrim'],
                'validators' => [['StringLength', true, ['min' => 1, 'max' => 64]]],
                'attribs' => array_merge($control, ['id' => 'su-last', 'autocomplete' => 'family-name', 'placeholder' => 'Doe']),
            ]],
            ['text', 'company', [
                'required' => $this->_setupOrg,   // names the new org; a join-mode signup has none
                'filters' => ['StringTrim'],
                'validators' => [['StringLength', true, ['min' => 2, 'max' => 128]]],
                'attribs' => array_merge($control, ['id' => 'su-company', 'autocomplete' => 'organization', 'placeholder' => 'Acme Widgets, Inc.']),
            ]],

            ['text', 'username', [
                'required' => true, 'filters' => ['StringTrim'],
                'validators' => [
                    ['Regex', true, ['pattern' => '/^[a-zA-Z0-9._-]{3,32}$/', 'messages' => [Zend_Validate_Regex::NOT_MATCH => '3–32 letters, numbers, dot, dash or underscore.']]],
                    $uniqueUsername,
                ],
                'attribs' => array_merge($control, ['id' => 'su-username', 'autocomplete' => 'username', 'placeholder' => 'janedoe']),
            ]],
            ['password', 'password', [
                'required' => true,
                'validators' => [new Tiger_Validate_Password()],
                // data-tiger-strength -> the live meter owns the inline UX; data-no-validate ->
                // TigerValidateJS skips it (the meter handles blur), but the server policy above
                // still validates it at submit.
                'attribs' => array_merge($control, ['id' => 'su-password', 'autocomplete' => 'new-password', 'data-tiger-strength' => '1', 'data-no-validate' => '1']),
            ]],
            ['text', 'email', [
                'required' => true, 'filters' => ['StringTrim', 'StringToLower'],
                // Format-only email check — ALLOW_ALL skips the wonky TLD/DNS hostname list
                // (which rejects perfectly good addresses like *.test / new TLDs). Uniqueness
                // is the NoRecordExists below.
                'validators' => [['EmailAddress', true, ['allow' => Zend_Validate_Hostname::ALLOW_ALL]], $uniqueEmail],
                'attribs' => array_merge($control, ['id' => 'su-email', 'type' => 'email', 'autocomplete' => 'email', 'placeholder' => 'jane@acme.com']),
            ]],

            ['text', 'street', [
                'required' => true, 'filters' => ['StringTrim'],
                'validators' => [['StringLength', true, ['min' => 3, 'max' => 191]]],
                // Address autocomplete (Tiger Location Service): picking a suggestion fills
                // the street + the mapped city/region/postal/country fields.
                'attribs' => array_merge($control, [
                    'id' => 'su-street', 'autocomplete' => 'address-line1', 'placeholder' => '123 Main St',
                    'data-tiger-address' => '1',
                    'data-fill-city' => 'su-city', 'data-fill-region' => 'su-region',
                    'data-fill-postal' => 'su-postal', 'data-fill-country' => 'su-country',
                ]),
            ]],
            ['text', 'city', [
                'required' => true, 'filters' => ['StringTrim'],
                'attribs' => array_merge($control, ['id' => 'su-city', 'autocomplete' => 'address-level2']),
            ]],
            ['text', 'region', [
                'required' => true, 'filters' => ['StringTrim'],
                'attribs' => array_merge($control, ['id' => 'su-region', 'autocomplete' => 'address-level1', 'placeholder' => 'State / Province']),
            ]],
            ['text', 'postal', [
                'required' => true, 'filters' => ['StringTrim'],
                'validators' => [['Regex', true, ['pattern' => '/^[A-Za-z0-9 -]{3,12}$/', 'messages' => [Zend_Validate_Regex::NOT_MATCH => 'Enter a valid postal code.']]]],
                'attribs' => array_merge($control, ['id' => 'su-postal', 'autocomplete' => 'postal-code']),
            ]],
            ['select', 'country', [
                'required' => true,
                // The <optgroup> select is rendered in the view (Tiger_I18n_Country::grouped);
                // validate the value explicitly and skip the auto multiOptions InArray.
                'registerInArrayValidator' => false,
                'validators' => [['InArray', true, ['haystack' => $countryCodes, 'messages' => [Zend_Validate_InArray::NOT_IN_ARRAY => 'Please choose a country.']]]],
                'attribs' => ['id' => 'su-country'],
            ]],

            ['select', 'phone_type', [
                'required' => true, 'multiOptions' => self::$_phoneTypes, 'value' => 'mobile',
                'validators' => [['InArray', true, ['haystack' => array_keys(self::$_phoneTypes)]]],
                'attribs' => array_merge($select, ['id' => 'su-phone-type']),
            ]],
            ['text', 'phone', [
                'required' => true, 'filters' => ['StringTrim'],
                'validators' => [['Regex', true, ['pattern' => '/^[0-9+().\- ]{7,20}$/', 'messages' => [Zend_Validate_Regex::NOT_MATCH => 'Enter a valid phone number.']]]],
                'attribs' => array_merge($control, ['id' => 'su-phone', 'type' => 'tel', 'autocomplete' => 'tel', 'placeholder' => '+1 555 123 4567']),
            ]],
        ];
    }
}
