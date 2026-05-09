# Linux Remote Manager

Sistema web em PHP para **ligar, controlar e monitorar máquinas Linux remotamente** via Wake-on-LAN e SSH.

---

## Funcionalidades

- **Wake-on-LAN** — liga máquinas remotamente pelo endereço MAC via Magic Packet UDP
- **Terminal SSH via browser** — executa comandos em qualquer máquina Linux diretamente pelo painel web
- **Monitor de sistema** — CPU, RAM, disco, uptime, hostname, processos em tempo real
- **Gerenciamento de máquinas** — cadastro, edição e remoção com autenticação por senha ou chave SSH
- **Dashboard** — visão geral de status online/offline de todas as máquinas
- **Reboot / Shutdown** remoto via SSH
- **Autenticação** — login com sessão protegida por bcrypt
- **Histórico de comandos** — navegação com setas ↑↓ no terminal

---

## Stack

- **Backend:** PHP 8.x · extensão `php-ssh2` · sockets UDP
- **Frontend:** HTML · CSS · JavaScript vanilla (sem dependências)
- **Dados:** JSON local (sem banco de dados)
- **Servidor:** Apache ou Nginx + PHP-FPM

---

## Estrutura do Projeto

```
linux-remote-manager/
├── index.html              # Interface web (painel de controle)
├── .htaccess               # Segurança e restrição de acesso
├── config/
│   └── config.php          # Configurações (credenciais, rede, timeouts)
├── api/
│   ├── handler.php         # API REST — roteador de todas as ações
│   ├── WakeOnLan.php       # Envio de Magic Packet UDP
│   ├── SSH.php             # Conexão e execução de comandos SSH
│   └── MachineManager.php  # CRUD de máquinas em JSON
└── data/
    └── machines.json       # Gerado automaticamente na primeira execução
```

---

## Requisitos

- PHP 8.0 ou superior
- Extensão `php-ssh2` instalada
- Apache ou Nginx
- Rede local com suporte a broadcast UDP (para Wake-on-LAN)
- Máquinas-alvo com Wake-on-LAN habilitado na BIOS/UEFI e servidor SSH ativo

---

## Instalação

### 1. Clone o repositório

```bash
git clone https://github.com/Leandro-Moreira-dS/linux-remote-manager.git
cd linux-remote-manager
```

### 2. Instale a extensão SSH para PHP

```bash
# Ubuntu / Debian
sudo apt update
sudo apt install php-ssh2 libssh2-1-dev

# Reinicie o servidor web
sudo systemctl restart apache2
# ou
sudo systemctl restart php8.x-fpm nginx
```

### 3. Configure o projeto

Edite `config/config.php`:

```php
// Credenciais do painel web
define('PANEL_USER', 'admin');
define('PANEL_PASS', password_hash('SUA_SENHA_AQUI', PASSWORD_BCRYPT));

// IP de broadcast da sua rede
// Exemplo rede 192.168.1.x → broadcast é 192.168.1.255
define('WOL_BROADCAST', '192.168.1.255');
```

### 4. Ajuste permissões

```bash
chmod 755 data/
chmod 644 data/machines.json  # criado automaticamente
```

### 5. Configure o virtualhost (Apache)

```apache
<VirtualHost *:80>
    ServerName lrm.local
    DocumentRoot /var/www/linux-remote-manager

    <Directory /var/www/linux-remote-manager>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

---

## Habilitar Wake-on-LAN nas máquinas-alvo

### Na BIOS/UEFI
Ative a opção **Wake-on-LAN** ou **Power On By PCI-E / Network**.

### No Linux (persistente)

```bash
# Descubra o nome da interface de rede
ip link show

# Habilite WoL na interface (substitua eth0 pelo nome real)
sudo ethtool -s eth0 wol g

# Para tornar permanente, crie o serviço systemd:
sudo nano /etc/systemd/system/wol.service
```

```ini
[Unit]
Description=Enable Wake-on-LAN
After=network.target

[Service]
Type=oneshot
ExecStart=/sbin/ethtool -s eth0 wol g

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable wol.service
sudo systemctl start wol.service
```

---

## Uso

1. Acesse `http://seu-servidor/linux-remote-manager`
2. Faça login com as credenciais configuradas em `config.php`
3. Cadastre suas máquinas em **Máquinas → Nova Máquina**
4. Use **Wake-on-LAN** para ligar máquinas desligadas
5. Use **Terminal SSH** para executar comandos remotamente

---

## Segurança

- Use HTTPS em produção (Let's Encrypt + Certbot)
- Prefira autenticação por **chave SSH** em vez de senha
- O `.htaccess` restringe acesso a IPs da rede local por padrão
- Comandos destrutivos (`rm -rf /`, `mkfs`, etc.) são bloqueados pela API
- Sessões expiram após 1 hora (configurável em `config.php`)

---

## Autor

**Leandro Moreira**
[LinkedIn](https://www.linkedin.com/in/leandro-moreira-dos-santos/) · [WhatsApp](https://wa.me/5592991743165) · lemodosantos@gmail.com
