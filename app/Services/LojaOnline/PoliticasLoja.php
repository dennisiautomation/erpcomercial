<?php

namespace App\Services\LojaOnline;

use App\Models\ConfiguracaoLoja;
use App\Models\LojaOnline;

/**
 * Páginas de política da loja online (14/11/2026): privacidade, trocas e
 * devoluções, entrega e termos de uso.
 *
 * Cada uma tem um TEXTO DE PARTIDA montado com os dados da empresa e com o que a
 * loja realmente tem ligado (retirada, entrega, PIX, cartão, rastreadores). O
 * lojista pode trocar pelo texto dele em Loja online → Políticas e cookies. O
 * texto de partida é um modelo geral — não substitui a revisão de um advogado, e
 * a tela do lojista diz isso.
 */
class PoliticasLoja
{
    public const TIPOS = [
        'privacidade' => ['Política de privacidade', 'politica_privacidade'],
        'trocas' => ['Trocas e devoluções', 'texto_trocas'],
        'entrega' => ['Entrega e retirada', 'politica_entrega'],
        'termos' => ['Termos de uso', 'politica_termos'],
    ];

    public static function titulo(string $tipo): string
    {
        return self::TIPOS[$tipo][0];
    }

    public static function personalizada(LojaOnline $loja, string $tipo): bool
    {
        return filled($loja->opcao(self::TIPOS[$tipo][1]));
    }

    public static function texto(LojaOnline $loja, string $tipo): string
    {
        $proprio = trim((string) $loja->opcao(self::TIPOS[$tipo][1]));

        return $proprio !== '' ? $proprio : self::modelo($loja, $tipo);
    }

    /** Texto de partida. Parágrafos separados por linha em branco; linha que termina com ":" vira subtítulo. */
    public static function modelo(LojaOnline $loja, string $tipo): string
    {
        $empresa = $loja->empresa;
        $razao = $empresa?->razao_social ?: $loja->nome;
        $cnpj = $empresa?->cnpj ? ' (CNPJ ' . ConfiguracaoLoja::formatarDocumento($empresa->cnpj) . ')' : '';
        $contato = collect([
            filled($loja->opcao('email')) ? 'e-mail ' . $loja->opcao('email') : null,
            filled($loja->opcao('whatsapp')) ? 'WhatsApp ' . $loja->opcao('whatsapp') : null,
        ])->filter()->implode(' ou ') ?: 'os canais de atendimento da loja';
        $meios = PagamentoLoja::meios($loja);

        if ($tipo === 'privacidade') {
            $rastro = $loja->temRastreamento()
                ? "Com a sua permissão, usamos também cookies de medição e de publicidade (" . collect([
                    filled($loja->opcao('ga4')) || filled($loja->opcao('gtm')) ? 'Google Analytics' : null,
                    filled($loja->opcao('google_ads')) ? 'Google Ads' : null,
                    filled($loja->opcao('meta_pixel')) ? 'Meta (Facebook e Instagram)' : null,
                    filled($loja->opcao('tiktok_pixel')) ? 'TikTok' : null,
                ])->filter()->implode(', ') . ") para entender como a loja é usada e mostrar anúncios mais relevantes. Você escolhe isso no aviso de cookies e pode mudar de ideia a qualquer momento pelo link \"Preferências de cookies\", no rodapé."
                : 'Não usamos cookies de publicidade nem de medição de terceiros.';

            return implode("\n\n", [
                "Esta política explica como {$razao}{$cnpj}, responsável pela loja {$loja->nome}, trata os seus dados pessoais, conforme a Lei Geral de Proteção de Dados (Lei 13.709/2018).",
                "Quais dados coletamos:",
                "Os que você informa ao comprar: nome, telefone ou WhatsApp, e-mail, CPF ou CNPJ (quando necessário para a nota fiscal) e endereço de entrega. Se você se cadastrar para receber novidades, guardamos o e-mail ou WhatsApp informado e a data do seu aceite.",
                "Para que usamos:",
                "Para registrar e entregar o seu pedido, emitir a nota fiscal, falar com você sobre a compra e cumprir obrigações legais. Só enviamos novidades e ofertas se você pedir, e você pode cancelar quando quiser.",
                "Com quem compartilhamos:",
                "Com as empresas que participam da sua compra, e apenas o necessário: o parceiro de pagamento" . ($meios['pix'] || $meios['cartao'] ? ' (PIX e cartão)' : '') . ", a transportadora ou o entregador e os órgãos fiscais. Não vendemos os seus dados. Os dados de cartão são digitados na página do parceiro de pagamento — a loja não os recebe nem armazena.",
                "Cookies:",
                "Usamos cookies necessários para a loja funcionar, como o que guarda o seu carrinho. " . $rastro,
                "Por quanto tempo guardamos:",
                "Enquanto durar a nossa relação com você e pelos prazos que a lei exige para documentos fiscais e de consumo.",
                "Seus direitos:",
                "Você pode pedir para confirmar, acessar, corrigir ou excluir os seus dados, e retirar um consentimento que tenha dado. Fale com a gente por {$contato}.",
            ]);
        }

        if ($tipo === 'trocas') {
            return implode("\n\n", [
                "Direito de arrependimento:",
                "Em compras feitas pela internet, você pode desistir em até 7 dias corridos depois de receber o produto, sem precisar explicar o motivo, conforme o artigo 49 do Código de Defesa do Consumidor. O valor pago, incluindo o frete, é devolvido.",
                "Produto com defeito:",
                "Se o produto apresentar defeito, você tem 30 dias (produtos não duráveis) ou 90 dias (produtos duráveis) para reclamar, contados do recebimento, conforme o artigo 26 do Código de Defesa do Consumidor.",
                "Como pedir:",
                "Fale com a loja por {$contato}, informando o número do pedido. Combinamos com você a devolução ou a troca e a forma de reembolso.",
            ]);
        }

        if ($tipo === 'entrega') {
            $unidade = $loja->unidade;
            $endereco = $unidade ? collect([trim(($unidade->logradouro ?? '') . ' ' . ($unidade->numero ?? '')), $unidade->bairro, trim(($unidade->cidade ?? '') . ($unidade->uf ? '/' . $unidade->uf : ''))])->filter()->implode(', ') : '';
            $partes = [];
            if ($loja->opcao('entrega')) {
                $partes[] = "Entrega no endereço:";
                $partes[] = "O valor e o prazo do frete são calculados pelo CEP e aparecem antes de você fechar o pedido. Quando o cálculo automático não estiver disponível para o seu endereço, a loja combina a entrega com você." . ((float) $loja->opcao('frete_gratis_acima') > 0 ? ' Compras a partir de R$ ' . number_format((float) $loja->opcao('frete_gratis_acima'), 2, ',', '.') . ' têm frete grátis.' : '');
            }
            if ($loja->opcao('retirada')) {
                $partes[] = "Retirada na loja:";
                $partes[] = 'Você pode retirar o pedido sem pagar frete' . ($endereco !== '' ? ', em ' . $endereco : '') . '. Aguarde o aviso de que o pedido está pronto.' . (filled($loja->opcao('horario_atendimento')) ? ' Horário: ' . $loja->opcao('horario_atendimento') . '.' : '');
            }
            $partes[] = "Acompanhamento:";
            $partes[] = "Ao fechar a compra você recebe o endereço do pedido, por onde acompanha cada etapa.";

            return implode("\n\n", $partes);
        }

        return implode("\n\n", [
            "Estes termos valem para as compras feitas na loja {$loja->nome}, de {$razao}{$cnpj}.",
            "Produtos e preços:",
            "Os preços e a disponibilidade são os mostrados na loja no momento da compra e podem mudar sem aviso. Promoções valem enquanto estiverem publicadas ou enquanto durar o estoque.",
            "Pedido:",
            "O pedido é registrado quando você conclui o fechamento. A loja pode cancelar um pedido se o produto não estiver mais disponível ou se houver erro evidente de preço, devolvendo o que foi pago.",
            "Pagamento:",
            'As formas de pagamento aceitas aparecem no fechamento do pedido' . ($meios['pix'] || $meios['cartao'] ? ' e são processadas por parceiros de pagamento' : '') . '.',
            "Atendimento:",
            "Dúvidas e reclamações: {$contato}.",
        ]);
    }
}
