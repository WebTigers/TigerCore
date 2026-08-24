<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Identity module — Brazilian Portuguese strings (identity.*).
 */
return [
    // Service / API messages
    'identity.saved'            => 'Identidade do site salva.',

    // Form placeholders
    'identity.field.site_name'  => 'ex.: Acme, Inc.',
    'identity.field.tagline'    => 'Uma linha breve abaixo do nome',
    'identity.field.site_description' => 'ex.: A Acme publica guias de campo para leitores curiosos.',

    // Site Identity screen
    'identity.page.title'       => 'Identidade do site',
    'identity.page.subtitle'    => 'O nome, o logotipo, o favicon e os perfis sociais do seu site: a marca que aparece nas abas do navegador, nos resultados de busca e nos compartilhamentos em redes sociais.',
    'identity.action.save'      => 'Salvar',
    'identity.card.identity'    => 'Identidade',
    'identity.label.site_name'  => 'Nome do site',
    'identity.help.site_name'   => 'Exibido no cabeçalho do site e na aba do navegador, e usado como título de página padrão e nome da marca nos resultados de busca.',
    'identity.label.tagline'    => 'Slogan',
    'identity.help.tagline'     => 'Uma linha breve que descreve o site (opcional).',
    'identity.label.site_description' => 'Descrição do site',
    'identity.help.site_description'  => 'Uma ou duas frases sobre o site. Usada como descrição padrão nos resultados de busca e nos cartões de compartilhamento em redes sociais quando a página não tem a sua própria.',
    'identity.card.logo_favicon' => 'Logotipo e favicon',
    'identity.label.logo'       => 'Logotipo',
    'identity.label.favicon'    => 'Favicon',
    'identity.help.logo'        => 'Usado para sua marca nos resultados de busca (esquema de Organização) e disponível para os temas.',
    'identity.help.favicon'     => 'O pequeno ícone na aba do navegador. Use uma imagem <strong>quadrada</strong>: 512&times;512 ou maior é ideal; o navegador a reduz para todos os tamanhos de que precisa.',
    'identity.card.share_image'  => 'Imagem de compartilhamento',
    'identity.help.share_image'  => 'A imagem exibida quando uma página deste site é compartilhada nas redes sociais (Open Graph). Escolha uma na Biblioteca de mídia para obter o melhor cartão — o tamanho real dela é publicado junto — ou cole o endereço de uma imagem hospedada em outro lugar. Se definir os dois, a imagem da biblioteca prevalece. 1200 × 630 pixels funciona em qualquer lugar.',
    'identity.label.og_image'    => 'Escolher imagem de compartilhamento',
    'identity.help.og_image'     => 'Escolha uma imagem na Biblioteca de mídia (recomendado).',
    'identity.label.og_image_url' => 'Ou o endereço de uma imagem',
    'identity.help.og_image_url'  => 'O endereço https:// completo de uma imagem hospedada em outro lugar. Usado apenas quando nenhuma imagem da biblioteca é escolhida.',
    'identity.card.social'      => 'Perfis sociais',
    'identity.help.social'      => 'URLs completas dos seus perfis oficiais. São publicadas como os links verificados da sua marca (schema.org <code>sameAs</code>): deixe qualquer uma em branco.',
    'identity.social.twitter'   => 'X / Twitter',
    'identity.social.facebook'  => 'Facebook',
    'identity.social.instagram' => 'Instagram',
    'identity.social.linkedin'  => 'LinkedIn',
    'identity.social.youtube'   => 'YouTube',
    'identity.social.github'    => 'GitHub',

    // JS-facing strings (registered via $this->i18n, resolved by Tiger.t)
    'identity.js.saved'         => 'Identidade do site salva.',
    'identity.js.fix_fields'    => 'Corrija os campos destacados.',
    'identity.js.network_error' => 'Erro de rede — tente novamente.',
    'identity.nav.label' => 'Identidade do site',
];
