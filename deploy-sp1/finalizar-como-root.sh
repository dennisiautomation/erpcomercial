#!/bin/bash
# Passos finais da migração erp.ia365.com.br — rodar como root em sp1-sd-iautomation-1
# Pré-requisito: DNS erp.ia365.com.br A -> 177.104.137.179 já propagado.
set -e

# 1. Mover app para o padrão do servidor (/root/<app>) — containers continuam rodando,
#    o compose reencontra o projeto pelo nome (erp-comercial-prod).
if [ -d /home/ubuntu/apps/erp-comercial ] && [ ! -d /root/erp ]; then
    mv /home/ubuntu/apps/erp-comercial /root/erp
    chown -R root:root /root/erp
fi

# 2. Site nginx
cp /root/erp/deploy-sp1/nginx-erp.ia365.com.br.conf /etc/nginx/sites-available/erp.ia365.com.br
ln -sf /etc/nginx/sites-available/erp.ia365.com.br /etc/nginx/sites-enabled/erp.ia365.com.br
nginx -t && systemctl reload nginx

# 3. Certificado (só depois do DNS apontar!)
certbot --nginx -d erp.ia365.com.br --redirect -m dcanteli@ia365.com.br --agree-tos -n

# 4. FortiGate: NADA a fazer — 80/443 já encaminhados para 192.168.100.2 (VIPs not-http/not-https).

# 5. Teste
curl -sI https://erp.ia365.com.br | head -5
echo "Lembrete: atualizar a tabela de apps na seção 7 do /root/CLAUDE.md"
