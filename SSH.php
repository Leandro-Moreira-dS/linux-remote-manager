<?php
// ============================================================
//  SSH — Executa comandos em máquinas Linux remotamente
//  Requer extensão PHP: php-ssh2 (libssh2)
//  Instalar: sudo apt install php-ssh2 && sudo systemctl restart apache2
// ============================================================

class SSH
{
    private $connection = null;
    private $session    = null;
    private string $host;
    private int    $port;
    private string $user;

    public function __construct(string $host, string $user, int $port = 22)
    {
        $this->host = $host;
        $this->user = $user;
        $this->port = $port;
    }

    /**
     * Conecta via senha
     */
    public function connectWithPassword(string $password): array
    {
        return $this->connect('password', $password);
    }

    /**
     * Conecta via chave SSH (mais seguro — recomendado para produção)
     *
     * @param string $publicKey  Caminho para ~/.ssh/id_rsa.pub
     * @param string $privateKey Caminho para ~/.ssh/id_rsa
     * @param string $passphrase Senha da chave (deixe '' se não tiver)
     */
    public function connectWithKey(string $publicKey, string $privateKey, string $passphrase = ''): array
    {
        return $this->connect('key', null, $publicKey, $privateKey, $passphrase);
    }

    private function connect(string $method, ?string $password = null,
                             string $pubKey = '', string $privKey = '', string $passphrase = ''): array
    {
        if (!function_exists('ssh2_connect')) {
            return ['success' => false, 'message' => 'Extensão php-ssh2 não instalada. Execute: sudo apt install php-ssh2'];
        }

        $this->connection = @ssh2_connect($this->host, $this->port);
        if (!$this->connection) {
            return ['success' => false, 'message' => "Não foi possível conectar a {$this->host}:{$this->port}"];
        }

        if ($method === 'password') {
            $auth = @ssh2_auth_password($this->connection, $this->user, $password);
        } else {
            $auth = @ssh2_auth_pubkey_file($this->connection, $this->user, $pubKey, $privKey, $passphrase);
        }

        if (!$auth) {
            return ['success' => false, 'message' => 'Autenticação SSH falhou. Verifique usuário e credenciais.'];
        }

        return ['success' => true, 'message' => 'Conectado com sucesso'];
    }

    /**
     * Executa um comando remoto e retorna a saída
     *
     * @param string $command  Comando a executar
     * @param int    $timeout  Timeout em segundos
     * @return array ['success' => bool, 'output' => string, 'error' => string]
     */
    public function exec(string $command, int $timeout = 10): array
    {
        if (!$this->connection) {
            return ['success' => false, 'output' => '', 'error' => 'Sem conexão SSH ativa'];
        }

        $stream = @ssh2_exec($this->connection, $command);
        if (!$stream) {
            return ['success' => false, 'output' => '', 'error' => 'Falha ao executar comando'];
        }

        $errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
        stream_set_blocking($stream, true);
        stream_set_blocking($errorStream, true);

        $output = stream_get_contents($stream);
        $error  = stream_get_contents($errorStream);

        fclose($stream);
        fclose($errorStream);

        return [
            'success' => empty($error),
            'output'  => trim($output),
            'error'   => trim($error),
        ];
    }

    /**
     * Comandos prontos de uso comum
     */
    public function getSystemInfo(): array
    {
        $commands = [
            'hostname'  => 'hostname',
            'uptime'    => 'uptime -p',
            'cpu'       => "top -bn1 | grep 'Cpu(s)' | awk '{print $2}' | cut -d'%' -f1",
            'ram'       => "free -m | awk 'NR==2{printf \"%s/%sMB (%.0f%%)\", $3,$2,$3*100/$2}'",
            'disk'      => "df -h / | awk 'NR==2{print $3\"/\"$2\" (\"$5\")\"}'",
            'ip'        => "hostname -I | awk '{print $1}'",
            'os'        => "cat /etc/os-release | grep PRETTY_NAME | cut -d'\"' -f2",
            'processes' => 'ps aux | wc -l',
        ];

        $info = [];
        foreach ($commands as $key => $cmd) {
            $result    = $this->exec($cmd);
            $info[$key] = $result['success'] ? $result['output'] : 'N/A';
        }

        return $info;
    }

    public function reboot(): array
    {
        return $this->exec('sudo reboot');
    }

    public function shutdown(): array
    {
        return $this->exec('sudo shutdown -h now');
    }

    public function getProcesses(): array
    {
        $result = $this->exec("ps aux --sort=-%cpu | head -11 | awk 'NR>1{printf \"%s|%s|%s|%s\\n\",$1,$11,$3,$4}'");
        if (!$result['success'] || empty($result['output'])) {
            return [];
        }

        $processes = [];
        foreach (explode("\n", $result['output']) as $line) {
            $parts = explode('|', $line);
            if (count($parts) === 4) {
                $processes[] = [
                    'user'    => $parts[0],
                    'command' => basename($parts[1]),
                    'cpu'     => $parts[2] . '%',
                    'mem'     => $parts[3] . '%',
                ];
            }
        }

        return $processes;
    }

    public function disconnect(): void
    {
        $this->connection = null;
    }
}
