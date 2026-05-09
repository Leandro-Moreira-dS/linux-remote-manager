<?php
// ============================================================
//  API REST — Todas as ações do painel (JSON)
//  Chamada: POST /api/handler.php
//  Body:    { "action": "...", ...params }
// ============================================================

session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/WakeOnLan.php';
require_once __DIR__ . '/SSH.php';
require_once __DIR__ . '/MachineManager.php';

// ── Autenticação ─────────────────────────────────────────────
function requireAuth(): void
{
    if (empty($_SESSION['authenticated'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }
}

function json(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// ── Lê o body JSON ───────────────────────────────────────────
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? ($_GET['action'] ?? '');

$manager = new MachineManager(MACHINES_FILE);

// ── Roteador de ações ────────────────────────────────────────
switch ($action) {

    // Login
    case 'login':
        $user = $body['user'] ?? '';
        $pass = $body['pass'] ?? '';
        if ($user === PANEL_USER && password_verify($pass, PANEL_PASS)) {
            $_SESSION['authenticated'] = true;
            $_SESSION['login_time']    = time();
            json(['success' => true, 'message' => 'Login realizado']);
        }
        json(['success' => false, 'message' => 'Usuário ou senha incorretos'], 401);

    // Logout
    case 'logout':
        session_destroy();
        json(['success' => true]);

    // Verifica sessão
    case 'check_session':
        $ok = !empty($_SESSION['authenticated']) &&
              (time() - ($_SESSION['login_time'] ?? 0)) < SESSION_TIMEOUT;
        json(['authenticated' => $ok]);

    // ── Máquinas ─────────────────────────────────────────────

    case 'machines_list':
        requireAuth();
        $machines = $manager->all();
        // Adiciona status online/offline
        foreach ($machines as &$m) {
            $m['online']   = $manager->isOnline($m['ip'], $m['port']);
            $m['mac_fmt']  = WakeOnLan::formatMac($m['mac']);
            $m['password'] = ''; // nunca retorna senha
        }
        json(['success' => true, 'machines' => $machines]);

    case 'machine_add':
        requireAuth();
        if (empty($body['name']) || empty($body['mac']) || empty($body['ip'])) {
            json(['success' => false, 'message' => 'Campos obrigatórios: name, mac, ip'], 400);
        }
        if (!WakeOnLan::isValid($body['mac'])) {
            json(['success' => false, 'message' => 'Endereço MAC inválido'], 400);
        }
        $machine = $manager->add($body);
        json(['success' => true, 'machine' => $machine]);

    case 'machine_update':
        requireAuth();
        $id = $body['id'] ?? '';
        if (!$id || !$manager->find($id)) {
            json(['success' => false, 'message' => 'Máquina não encontrada'], 404);
        }
        $manager->update($id, $body);
        json(['success' => true, 'message' => 'Máquina atualizada']);

    case 'machine_delete':
        requireAuth();
        $id = $body['id'] ?? '';
        if (!$id) json(['success' => false, 'message' => 'ID não informado'], 400);
        $manager->delete($id);
        json(['success' => true, 'message' => 'Máquina removida']);

    // ── Wake-on-LAN ──────────────────────────────────────────

    case 'wol_send':
        requireAuth();
        $id = $body['id'] ?? '';
        $machine = $manager->find($id);
        if (!$machine) json(['success' => false, 'message' => 'Máquina não encontrada'], 404);

        $result = WakeOnLan::send($machine['mac'], WOL_BROADCAST, WOL_PORT);
        json($result);

    // ── SSH ──────────────────────────────────────────────────

    case 'ssh_info':
        requireAuth();
        [$ssh, $err] = connectSSH($body, $manager);
        if ($err) json(['success' => false, 'message' => $err]);

        $info = $ssh->getSystemInfo();
        $ssh->disconnect();
        json(['success' => true, 'info' => $info]);

    case 'ssh_exec':
        requireAuth();
        $command = trim($body['command'] ?? '');
        if (empty($command)) json(['success' => false, 'message' => 'Comando vazio'], 400);

        // Bloqueia comandos destrutivos sem confirmação
        $blocked = ['rm -rf /', 'mkfs', 'dd if=/dev/zero', ':(){:|:&};:'];
        foreach ($blocked as $b) {
            if (str_contains($command, $b)) {
                json(['success' => false, 'message' => 'Comando bloqueado por segurança: ' . $b], 403);
            }
        }

        [$ssh, $err] = connectSSH($body, $manager);
        if ($err) json(['success' => false, 'message' => $err]);

        $result = $ssh->exec($command, SSH_TIMEOUT);
        $ssh->disconnect();
        json(['success' => true, 'output' => $result['output'], 'error' => $result['error']]);

    case 'ssh_processes':
        requireAuth();
        [$ssh, $err] = connectSSH($body, $manager);
        if ($err) json(['success' => false, 'message' => $err]);

        $processes = $ssh->getProcesses();
        $ssh->disconnect();
        json(['success' => true, 'processes' => $processes]);

    case 'ssh_reboot':
        requireAuth();
        [$ssh, $err] = connectSSH($body, $manager);
        if ($err) json(['success' => false, 'message' => $err]);
        $ssh->reboot();
        $ssh->disconnect();
        json(['success' => true, 'message' => 'Comando de reboot enviado']);

    case 'ssh_shutdown':
        requireAuth();
        [$ssh, $err] = connectSSH($body, $manager);
        if ($err) json(['success' => false, 'message' => $err]);
        $ssh->shutdown();
        $ssh->disconnect();
        json(['success' => true, 'message' => 'Comando de desligamento enviado']);

    // ── Status rápido ────────────────────────────────────────

    case 'ping':
        requireAuth();
        $id = $body['id'] ?? '';
        $machine = $manager->find($id);
        if (!$machine) json(['success' => false, 'message' => 'Máquina não encontrada'], 404);
        $online = $manager->isOnline($machine['ip'], $machine['port']);
        json(['success' => true, 'online' => $online]);

    default:
        json(['success' => false, 'message' => 'Ação desconhecida: ' . $action], 400);
}

// ── Helper: cria conexão SSH a partir do body ─────────────────
function connectSSH(array $body, MachineManager $manager): array
{
    $id = $body['id'] ?? '';
    $machine = $manager->find($id);
    if (!$machine) return [null, 'Máquina não encontrada'];

    $ssh = new SSH($machine['ip'], $machine['user'], $machine['port']);

    if ($machine['auth_type'] === 'key') {
        $result = $ssh->connectWithKey(
            $machine['key_path'] . '.pub',
            $machine['key_path'],
            $body['passphrase'] ?? ''
        );
    } else {
        $password = base64_decode($machine['password']);
        $result   = $ssh->connectWithPassword($password);
    }

    if (!$result['success']) return [null, $result['message']];

    return [$ssh, null];
}
