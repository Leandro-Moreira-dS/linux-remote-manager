<?php
// ============================================================
//  Linux Remote Manager — Configuração
// ============================================================

define('APP_VERSION', '1.0.0');
define('SESSION_TIMEOUT', 3600); // 1 hora

// Credenciais de acesso ao painel web
// TROQUE antes de subir em produção
define('PANEL_USER', 'admin');
define('PANEL_PASS', password_hash('senha123', PASSWORD_BCRYPT));

// Caminho para o arquivo de máquinas cadastradas
define('MACHINES_FILE', __DIR__ . '/../data/machines.json');

// Timeout padrão para comandos SSH (segundos)
define('SSH_TIMEOUT', 10);

// Porta padrão SSH
define('SSH_DEFAULT_PORT', 22);

// Broadcast de rede para Wake-on-LAN (ajuste para sua sub-rede)
// Exemplos: '192.168.1.255', '10.0.0.255', '255.255.255.255'
define('WOL_BROADCAST', '192.168.1.255');
define('WOL_PORT', 9);
