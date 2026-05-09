<?php
// ============================================================
//  MachineManager — CRUD de máquinas cadastradas (JSON)
// ============================================================

class MachineManager
{
    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;

        // Cria o diretório e arquivo se não existirem
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!file_exists($file)) {
            file_put_contents($file, json_encode([]));
        }
    }

    public function all(): array
    {
        $data = file_get_contents($this->file);
        return json_decode($data, true) ?? [];
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $machine) {
            if ($machine['id'] === $id) {
                return $machine;
            }
        }
        return null;
    }

    public function add(array $data): array
    {
        $machines = $this->all();

        $machine = [
            'id'         => uniqid('m_'),
            'name'       => trim($data['name']),
            'mac'        => strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $data['mac'])),
            'ip'         => trim($data['ip']),
            'port'       => (int)($data['port'] ?? 22),
            'user'       => trim($data['user'] ?? 'root'),
            'auth_type'  => $data['auth_type'] ?? 'password', // 'password' ou 'key'
            'password'   => isset($data['password']) ? base64_encode($data['password']) : '',
            'key_path'   => $data['key_path'] ?? '',
            'group'      => $data['group'] ?? 'Geral',
            'notes'      => $data['notes'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $machines[] = $machine;
        $this->save($machines);

        return $machine;
    }

    public function update(string $id, array $data): bool
    {
        $machines = $this->all();
        foreach ($machines as &$machine) {
            if ($machine['id'] === $id) {
                $machine['name']      = trim($data['name'] ?? $machine['name']);
                $machine['mac']       = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $data['mac'] ?? $machine['mac']));
                $machine['ip']        = trim($data['ip'] ?? $machine['ip']);
                $machine['port']      = (int)($data['port'] ?? $machine['port']);
                $machine['user']      = trim($data['user'] ?? $machine['user']);
                $machine['auth_type'] = $data['auth_type'] ?? $machine['auth_type'];
                $machine['group']     = $data['group'] ?? $machine['group'];
                $machine['notes']     = $data['notes'] ?? $machine['notes'];
                if (!empty($data['password'])) {
                    $machine['password'] = base64_encode($data['password']);
                }
                $this->save($machines);
                return true;
            }
        }
        return false;
    }

    public function delete(string $id): bool
    {
        $machines = array_filter($this->all(), fn($m) => $m['id'] !== $id);
        $this->save(array_values($machines));
        return true;
    }

    /**
     * Verifica se a máquina responde na porta SSH (está online)
     */
    public function isOnline(string $ip, int $port = 22, int $timeout = 2): bool
    {
        $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        if ($socket) {
            fclose($socket);
            return true;
        }
        return false;
    }

    private function save(array $machines): void
    {
        file_put_contents($this->file, json_encode($machines, JSON_PRETTY_PRINT));
    }
}
