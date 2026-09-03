#!/usr/bin/env python3
"""
Gera os ícones do PWA do ERP Comercial (marca = grade 3x3 do sidebar, bi-grid-3x3-gap-fill).

    python3 resources/pwa/gerar-icones.py

Saída em resources/pwa/ (NÃO em public/ — public/ não vai no deploy por tar,
ver armadilha 46; os ícones são servidos pelo PwaController).
"""
from PIL import Image, ImageDraw
import os

DIR = os.path.dirname(os.path.abspath(__file__))

# Mesmas cores do design system (--accent / --pdv-bg do layouts/app.blade.php)
DE = (99, 102, 241)    # #6366f1
PARA = (139, 92, 246)  # #8b5cf6

SS = 4  # supersampling para bordas suaves


def fundo(lado, raio_pct):
    """Quadrado arredondado com gradiente diagonal."""
    g = Image.new('RGB', (lado, lado))
    px = g.load()
    for y in range(lado):
        for x in range(lado):
            t = (x + y) / (2 * (lado - 1))
            px[x, y] = tuple(int(DE[i] + (PARA[i] - DE[i]) * t) for i in range(3))

    mascara = Image.new('L', (lado, lado), 0)
    ImageDraw.Draw(mascara).rounded_rectangle(
        [0, 0, lado - 1, lado - 1], radius=int(lado * raio_pct), fill=255
    )
    img = Image.new('RGBA', (lado, lado), (0, 0, 0, 0))
    img.paste(g, (0, 0), mascara)
    return img


def grade(img, area, cor=(255, 255, 255, 255)):
    """Desenha a grade 3x3 de quadradinhos dentro de `area` (x0, y0, x1, y1)."""
    x0, y0, x1, y1 = area
    lado = x1 - x0
    celula = lado / 3.0
    gap = celula * 0.22
    q = celula - gap
    d = ImageDraw.Draw(img)
    for lin in range(3):
        for col in range(3):
            cx = x0 + col * celula + gap / 2
            cy = y0 + lin * celula + gap / 2
            d.rounded_rectangle(
                [cx, cy, cx + q, cy + q], radius=q * 0.28, fill=cor
            )


def icone(lado, raio_pct=0.22, margem_pct=0.22, arquivo='icone.png'):
    big = lado * SS
    img = fundo(big, raio_pct)
    m = big * margem_pct
    grade(img, (m, m, big - m, big - m))
    img = img.resize((lado, lado), Image.LANCZOS)
    caminho = os.path.join(DIR, arquivo)
    img.save(caminho, 'PNG', optimize=True)
    print(f'{arquivo}: {os.path.getsize(caminho)} bytes')


if __name__ == '__main__':
    # purpose "any" — cantos arredondados, marca ocupando bastante área
    icone(192, 0.22, 0.22, 'icone-192.png')
    icone(512, 0.22, 0.22, 'icone-512.png')
    # apple-touch-icon: iOS já recorta, fundo cheio
    icone(180, 0.0, 0.22, 'icone-apple-180.png')
    # purpose "maskable" — quadrado cheio + safe zone de 40% (marca menor, centralizada)
    icone(512, 0.0, 0.30, 'icone-maskable-512.png')
