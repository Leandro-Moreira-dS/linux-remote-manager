<?php
// ============================================================
//  Wake-on-LAN — Envia Magic Packet pelo endereço MAC
// ============================================================

class WakeOnLan
{
    /**
     * Envia um Magic Packet para ligar a máquina remotamente.
     *
     * @param string $mac       Endereço MAC (formatos: AA:BB:CC:DD:EE:FF ou AA-BB-CC-DD-EE-FF)
     * @param string $broadcast IP de broadcast da rede (ex: 192.168.1.255)
     * @param int    $port      Porta UDP (padrão: 9)
     * @return array ['success' => bool, 'message' => string]
     */
    public static function send(string $mac, string $broadcast = '192.168.1.255', int $port = 9): array
    {
        // Normaliza o MAC removendo separadores
        $mac = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $mac));

        if (strlen($mac) !== 12) {
            return ['success' => false, 'message' => 'Endereço MAC inválido: ' . $mac];
        }

        // Constrói o Magic Packet:
        // 6 bytes 0xFF + 16 repetições do MAC = 102 bytes
        $packet = str_repeat(chr(0xFF), 6);
        $macBytes = '';
        for ($i = 0; $i < 12; $i += 2) {
            $macBytes .= chr(hexdec(substr($mac, $i, 2)));
        }
        $packet .= str_repeat($macBytes, 16);

        // Envia via UDP
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket === false) {
            return ['success' => false, 'message' => 'Erro ao criar socket UDP'];
        }

        socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        $result = socket_sendto($socket, $packet, strlen($packet), 0, $broadcast, $port);
        socket_close($socket);

        if ($result === false) {
            return ['success' => false, 'message' => 'Falha ao enviar Magic Packet'];
        }

        return ['success' => true, 'message' => 'Magic Packet enviado para ' . self::formatMac($mac)];
    }

    /**
     * Formata MAC para exibição: AABBCCDDEEFF → AA:BB:CC:DD:EE:FF
     */
    public static function formatMac(string $mac): string
    {
        $mac = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $mac));
        return implode(':', str_split($mac, 2));
    }

    /**
     * Valida se o MAC tem formato correto
     */
    public static function isValid(string $mac): bool
    {
        $mac = preg_replace('/[^A-Fa-f0-9]/', '', $mac);
        return strlen($mac) === 12;
    }
}
