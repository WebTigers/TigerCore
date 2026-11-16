<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Analytics_Widget_Ga — the pre-1.61 name of the "Traffic" dashboard widget, kept so any code or saved
 * descriptor that names it still resolves. The widget is provider-agnostic now: everything lives in
 * Analytics_Widget_Traffic (the class the Bootstrap registers under the unchanged id `analytics.traffic`).
 *
 * @api
 * @deprecated 1.61.0 use Analytics_Widget_Traffic
 */
class Analytics_Widget_Ga extends Analytics_Widget_Traffic
{
}
