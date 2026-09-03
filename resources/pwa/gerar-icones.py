#!/usr/bin/env python3
"""
Gera os ícones do PWA do ERP Comercial — marca "IA / ERP", a mesma da landing
(`.brand__mark` = quadradinho com "IA", seguido de "ERP").

    python3 resources/pwa/gerar-icones.py            # padrão: gradiente do sistema
    python3 resources/pwa/gerar-icones.py ink        # variante Apple (#1d1d1f), igual à landing

Saída em resources/pwa/ (NÃO em public/ — public/ não vai no deploy por tar,
ver armadilha 46; os ícones são servidos pelo PwaController).
"""
from PIL import Image, ImageDraw, ImageFont
import os
import sys

DIR = os.path.dirname(os.path.abspath(__file__))
FONTE = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'

# Paleta do design system (--accent / --pdv-bg do layouts/app.blade.php)
DE = (99, 102, 241)    # #6366f1
PARA = (139, 92, 246)  # #8b5cf6
# Paleta da landing V2 (--ink)
INK = (29, 29, 31)     # #1d1d1f

SS = 4  # supersampling para bordas e letras suaves


def fundo(lado, raio_pct, tema):
    """Quadrado arredondado: gradiente diagonal (tema 'gradiente') ou chapado (tema 'ink')."""
    if tema == 'ink':
        base = Image.new('RGB', (lado, lado), INK)
    else:
        base = Image.new('RGB', (lado, lado))
        px = base.load()
        for y in range(lado):
            for x in range(lado):
                t = (x + y) / (2 * (lado - 1))
                px[x, y] = tuple(int(DE[i] + (PARA[i] - DE[i]) * t) for i in range(3))

    mascara = Image.new('L', (lado, lado), 0)
    ImageDraw.Draw(mascara).rounded_rectangle(
        [0, 0, lado - 1, lado - 1], radius=int(lado * raio_pct), fill=255
    )
    img = Image.new('RGBA', (lado, lado), (0, 0, 0, 0))
    img.paste(base, (0, 0), mascara)
    return img


def texto_centrado(d, cy, txt, fonte, tracking=0, cor=(255, 255, 255, 255), largura=0):
    """Desenha `txt` centrado horizontalmente, com tracking manual, e devolve a altura usada."""
    larguras = [d.textlength(c, font=fonte) for c in txt]
    total = sum(larguras) + tracking * (len(txt) - 1)
    x = (largura - total) / 2
    caixa = fonte.getbbox(txt)
    y = cy - (caixa[1] + caixa[3]) / 2
    for c, w in zip(txt, larguras):
        d.text((x, y), c, font=fonte, fill=cor)
        x += w + tracking
    return caixa[3] - caixa[1]


def marca(img, lado, margem_pct):
    """'IA' em cima, 'ERP' embaixo — o lockup da landing empilhado para caber no quadrado."""
    d = ImageDraw.Draw(img)
    util = lado * (1 - 2 * margem_pct)

    f_ia  = ImageFont.truetype(FONTE, int(util * 0.52))
    f_erp = ImageFont.truetype(FONTE, int(util * 0.26))

    centro = lado / 2
    bloco  = util * 0.78                     # altura ocupada pelas duas linhas
    y_ia   = centro - bloco * 0.20
    y_erp  = centro + bloco * 0.30

    texto_centrado(d, y_ia,  'IA',  f_ia,  tracking=-util * 0.015, largura=lado)
    texto_centrado(d, y_erp, 'ERP', f_erp, tracking=util * 0.045,
                   cor=(255, 255, 255, 235), largura=lado)


def icone(lado, raio_pct, margem_pct, arquivo, tema, salvar=True):
    big = lado * SS
    img = fundo(big, raio_pct, tema)
    marca(img, big, margem_pct)
    img = img.resize((lado, lado), Image.LANCZOS)
    if salvar:
        caminho = os.path.join(DIR, arquivo)
        img.save(caminho, 'PNG', optimize=True)
        print(f'{arquivo}: {os.path.getsize(caminho)} bytes')
    return img


def gerar(tema='gradiente'):
    # purpose "any" — cantos arredondados
    icone(192, 0.22, 0.20, 'icone-192.png', tema)
    icone(512, 0.22, 0.20, 'icone-512.png', tema)
    # apple-touch-icon: iOS recorta sozinho, fundo cheio
    icone(180, 0.0, 0.20, 'icone-apple-180.png', tema)
    # purpose "maskable" — quadrado cheio + safe zone (Windows/Android recortam)
    icone(512, 0.0, 0.30, 'icone-maskable-512.png', tema)


if __name__ == '__main__':
    gerar(sys.argv[1] if len(sys.argv) > 1 else 'gradiente')
