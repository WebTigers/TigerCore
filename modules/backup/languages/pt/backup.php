<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
return [
    // Service / API messages
    'backup.done'             => 'Backup concluído.',
    'backup.failed'           => 'O backup falhou. Verifique os registros para mais detalhes.',
    'backup.deleted'          => 'Backup excluído.',
    'backup.restore.done'     => 'Restauração concluída.',
    'backup.restore.failed'   => 'A restauração falhou. Seu backup de segurança anterior está disponível.',
    'backup.restore.confirm'  => 'Digite RESTORE para confirmar esta ação destrutiva.',
    'backup.settings.saved'   => 'Configurações de backup salvas.',
    'backup.bad_component'    => 'Selecione pelo menos um componente para fazer backup.',
    'backup.bad_disk'         => 'Destino de backup desconhecido.',
    'backup.bad_email'        => 'Insira endereços de e-mail válidos, separados por vírgula.',
    'backup.not_found'        => 'Esse backup não foi encontrado.',
    'backup.upload.failed'    => 'O upload não foi concluído.',
    'backup.upload.invalid'   => 'Esse arquivo não é um arquivo do TigerBackup.',

    // Component labels
    'backup.comp.database'      => 'Banco de dados',
    'backup.comp.database_desc' => 'Todas as tabelas — um dump SQL portátil',
    'backup.comp.media'         => 'Mídia',
    'backup.comp.media_desc'    => 'Arquivos enviados',
    'backup.comp.modules'       => 'Módulos',
    'backup.comp.modules_desc'  => 'Os módulos do seu aplicativo',
    'backup.comp.platform'      => 'Plataforma',
    'backup.comp.platform_desc' => 'Código do aplicativo + configuração (para mover um site)',

    // Outcome badges
    'backup.outcome.ok'      => 'OK',
    'backup.outcome.error'   => 'Falhou',
    'backup.outcome.running' => 'Em execução',

    // Screen header
    'backup.title'      => 'Backup e restauração',
    'backup.subtitle'   => 'Arquive seu site em um zip para download — mantenha-o local ou envie-o para o armazenamento na nuvem. Restaure aqui, ou mova o site para um novo lar.',
    'backup.action.run' => 'Fazer backup agora',

    // Create card
    'backup.card.create'             => 'Criar um backup',
    'backup.create.include_label'     => 'O que incluir',
    'backup.create.destination_label' => 'Destino',
    'backup.create.destination_help'  => 'Configure um <em>disco de mídia</em> na nuvem (S3/GCS/Azure) para fazer backup fora do servidor.',
    'backup.create.secrets_label'     => 'Incluir segredos (local.ini)',
    'backup.create.secrets_help'      => 'Necessário para mover um site intacto. Manuseie o arquivo com segurança.',

    // Restore-from-a-file card
    'backup.card.restore_file'   => 'Restaurar de um arquivo',
    'backup.restore_file.help'   => 'Envie um <code>TigerBackup-*.zip</code> para restaurá-lo aqui — a forma de mover um site para uma instalação nova. Isso é <strong>destrutivo</strong>: é executado em modo de manutenção e faz um backup de segurança primeiro.',
    'backup.action.restore'      => 'Restaurar',

    // History card
    'backup.card.history'         => 'Backups',
    'backup.history.empty'        => 'Ainda não há backups. Escolha o que incluir e clique em <strong>Fazer backup agora</strong>.',
    'backup.col.archive'          => 'Arquivo',
    'backup.col.size'             => 'Tamanho',
    'backup.col.includes'         => 'Inclui',
    'backup.col.when'             => 'Quando',
    'backup.col.where'            => 'Onde',
    'backup.col.actions'          => 'Ações',
    'backup.pinned_title'         => 'Os backups manuais nunca são removidos automaticamente',
    'backup.action.download_title' => 'Baixar',
    'backup.action.restore_title'  => 'Restaurar este backup',
    'backup.action.delete_title'   => 'Excluir',

    // Scheduled backups card
    'backup.card.scheduled'          => 'Backups agendados',
    'backup.scheduled.help'          => 'Defina uma cadência e o Tiger faz backups sozinho. A retenção contínua mantém os <strong>N</strong> backups agendados mais recentes; os backups manuais nunca são removidos automaticamente.',
    'backup.scheduled.schedule_label' => 'Agendamento',
    'backup.scheduled.retention_label' => 'Manter os mais recentes (máximo contínuo)',
    'backup.scheduled.retention_help' => '0 = manter todos.',
    'backup.scheduled.email_label'    => 'Enviar status por e-mail para',
    'backup.scheduled.notify_label'   => 'Enviar e-mail em caso de sucesso e de falha',
    'backup.scheduled.note'           => 'Os backups agendados usam os componentes e o destino selecionados em <em>Criar um backup</em> acima, salvos aqui:',
    'backup.action.save_settings'     => 'Salvar configurações de agendamento',

    // Restore confirm modal
    'backup.restore_modal.title'         => 'Confirmar restauração',
    'backup.action.close'                => 'Fechar',
    'backup.restore_modal.body_pre'      => 'Você está prestes a restaurar ',
    'backup.restore_modal.body_post'     => '. Isso <strong>sobrescreve</strong> o banco de dados e/ou os arquivos atuais e não pode ser desfeito. Primeiro é feito um backup de segurança, e o site entra em modo de manutenção durante a restauração.',
    'backup.restore_modal.confirm_label' => 'Digite <code>RESTORE</code> para confirmar',
    'backup.action.cancel'               => 'Cancelar',
    'backup.action.restore_now'          => 'Restaurar agora',
    'backup.js.select_component'     => 'Selecione pelo menos um componente.',
    'backup.js.confirm_delete_named' => 'Excluir %s? Isso remove o arquivo permanentemente.',
    'backup.js.choose_zip'           => 'Escolha primeiro um arquivo .zip.',
    'backup.nav.label' => 'Backup',
];
