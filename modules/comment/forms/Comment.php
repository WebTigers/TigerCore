<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comment_Form_Comment — the post-a-comment input contract.
 *
 * Built dynamically from two facts the SUBJECT decides, not the form: whether stars apply, and
 * whether the author is a guest. That keeps one form for "a blog comment" and "a product review"
 * — they differ only in which elements exist.
 *
 * The validators declared here also power convenience (on-blur) validation for free, and are what
 * `/api/openapi` would reflect as the request schema.
 */
class Comment_Form_Comment extends Tiger_Form
{
    /** @var bool does this subject accept star ratings? */
    protected $_ratings = false;

    /** @var bool is the author signed out (so name + email are required)? */
    protected $_guest = false;

    /**
     * @param array|null $options `ratings` (bool), `guest` (bool), plus Zend_Form options
     */
    public function __construct($options = null)
    {
        if (is_array($options)) {
            $this->_ratings = !empty($options['ratings']);
            $this->_guest   = !empty($options['guest']);
            unset($options['ratings'], $options['guest']);
        }
        parent::__construct($options);
    }

    /** @return array the element schema */
    protected function elements(): array
    {
        $elements = [
            ['textarea', 'body', [
                'required'   => false,   // a star-only rating is legitimate; the service enforces "not both empty"
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, ['max' => 20000]]],
                'attribs'    => [
                    'class'       => 'form-control',
                    'rows'        => 4,
                    'placeholder' => $this->_t('comment.form.body'),
                ],
            ]],
        ];

        if ($this->_ratings) {
            $elements[] = ['select', 'rating', [
                'required'     => false,
                'multiOptions' => ['' => $this->_t('comment.form.rating_none'), 5 => '5', 4 => '4', 3 => '3', 2 => '2', 1 => '1'],
                'validators'   => [['Between', false, ['min' => 1, 'max' => 5]]],
                'attribs'      => ['class' => 'form-select'],
            ]];
        }

        if ($this->_guest) {
            $elements[] = ['text', 'author_name', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, ['min' => 2, 'max' => 191]]],
                'attribs'    => ['class' => 'form-control', 'placeholder' => $this->_t('comment.form.name')],
            ]];
            $elements[] = ['text', 'author_email', [
                'required'   => true,
                'filters'    => ['StringTrim', 'StringToLower'],
                'validators' => [['EmailAddress', false, ['allow' => Zend_Validate_Hostname::ALLOW_DNS]]],
                'attribs'    => ['class' => 'form-control', 'placeholder' => $this->_t('comment.form.email')],
            ]];
        }

        return $elements;
    }
}
