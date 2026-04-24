/**
 * Conteúdo do modal de ajuda fiscal brasileiro.
 *
 * Fontes oficiais: CONFAZ (Ajustes SINIEF 07/2001, 03/2010, 20/2012;
 * Convênios ICMS 92/2015, 142/2018), TIPI (Dec. 11.158/2022),
 * Leis 10.637/2002, 10.833/2003, EC 132/2023, LC 214/2025.
 *
 * Servido como asset estático para não sobrecarregar o compilador Blade
 * (o conteúdo é ~35KB de HTML embutido).
 */
(function () {
    if (window.ErpFiscalHelp) return;

    const CONTENT = {
        ncm: {
            title: 'NCM — Nomenclatura Comum do Mercosul',
            html: `
                <p><strong>Código de 8 dígitos</strong> (formato <code>XXXX.XX.XX</code>) que classifica mercadorias para fins de tributação. Adotado pelos países do Mercosul desde 1995, baseado no Sistema Harmonizado (SH) da OMA.</p>
                <p><strong>Obrigatório na NF-e</strong> desde a NT 2016.001 (01/07/2017) — exceto serviços.</p>
                <h6 class="mt-3">Estrutura</h6>
                <ul class="small">
                    <li>Dígitos 1–2: capítulo (SH)</li>
                    <li>Dígitos 3–4: posição</li>
                    <li>Dígitos 5–6: subposição</li>
                    <li>Dígitos 7–8: item e subitem (Mercosul)</li>
                </ul>
                <h6 class="mt-3">Exemplos comuns</h6>
                <table class="table table-sm small">
                    <thead><tr><th>NCM</th><th>Descrição</th></tr></thead>
                    <tbody>
                        <tr><td>0401.10.10</td><td>Leite UHT, teor de gordura ≤ 1%</td></tr>
                        <tr><td>1006.30.21</td><td>Arroz branco, parboilizado</td></tr>
                        <tr><td>2202.10.00</td><td>Refrigerantes</td></tr>
                        <tr><td>3004.90.69</td><td>Medicamentos — outros</td></tr>
                        <tr><td>6109.10.00</td><td>Camisetas, T-shirts, de malha de algodão</td></tr>
                        <tr><td>6403.99.90</td><td>Calçados de couro — outros</td></tr>
                        <tr><td>8517.13.00</td><td>Smartphones</td></tr>
                        <tr><td>9403.60.00</td><td>Móveis de madeira — outros</td></tr>
                    </tbody>
                </table>
                <h6 class="mt-3">Onde consultar</h6>
                <ul class="small">
                    <li><a href="https://www.planalto.gov.br/ccivil_03/_ato2019-2022/2022/decreto/D11158.htm" target="_blank" rel="noopener">TIPI — Decreto 11.158/2022</a> (tabela vigente)</li>
                    <li><a href="https://portalunico.siscomex.gov.br/classif/" target="_blank" rel="noopener">Portal Único Siscomex</a> (busca NCM)</li>
                    <li><a href="https://www.gov.br/receitafederal/pt-br/assuntos/aduana-e-comercio-exterior/classificacao-fiscal-de-mercadorias" target="_blank" rel="noopener">Receita Federal — Classificação Fiscal</a></li>
                </ul>
            `,
        },
        cest: {
            title: 'CEST — Código Especificador da Substituição Tributária',
            html: `
                <p>Código de <strong>7 dígitos</strong> (<code>SS.III.DD</code>) que identifica mercadoria passível de ICMS-ST ou antecipação, instituído pelo <strong>Convênio ICMS 92/2015</strong> e atualizado pelo <strong>Convênio ICMS 142/2018</strong>.</p>
                <div class="alert alert-warning small mb-3">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <strong>Obrigatório mesmo fora de ST:</strong> se o NCM do produto consta nos anexos do Convênio 142/2018, o CEST deve ser informado na NF-e, ainda que a operação específica não tenha ST.
                </div>
                <h6>Segmentos (anexos do Conv. 142/2018)</h6>
                <table class="table table-sm small">
                    <thead><tr><th>Anexo</th><th>Segmento</th></tr></thead>
                    <tbody>
                        <tr><td>II</td><td>Autopeças</td></tr>
                        <tr><td>III</td><td>Bebidas alcoólicas (exceto cerveja e chope)</td></tr>
                        <tr><td>IV</td><td>Cervejas, chopes, refrigerantes, águas</td></tr>
                        <tr><td>V</td><td>Cigarros e produtos derivados do fumo</td></tr>
                        <tr><td>VI</td><td>Cimentos</td></tr>
                        <tr><td>VII</td><td>Combustíveis e lubrificantes</td></tr>
                        <tr><td>IX</td><td>Cosméticos, perfumaria, higiene pessoal</td></tr>
                        <tr><td>X</td><td>Ferramentas</td></tr>
                        <tr><td>XII</td><td>Materiais de construção</td></tr>
                        <tr><td>XIII</td><td>Materiais elétricos</td></tr>
                        <tr><td>XIV</td><td>Medicamentos de uso humano</td></tr>
                        <tr><td>XVII</td><td>Produtos alimentícios</td></tr>
                        <tr><td>XXI</td><td>Tintas e vernizes</td></tr>
                        <tr><td>XXII</td><td>Veículos automotores</td></tr>
                    </tbody>
                </table>
                <h6 class="mt-3">Fontes oficiais</h6>
                <ul class="small">
                    <li><a href="https://www.confaz.fazenda.gov.br/legislacao/convenios/2018/CV142_18" target="_blank" rel="noopener">Convênio ICMS 142/2018</a></li>
                    <li><a href="https://www.confaz.fazenda.gov.br/legislacao/convenios/2015/cv092_15" target="_blank" rel="noopener">Convênio ICMS 92/2015</a></li>
                </ul>
            `,
        },
        cfop: {
            title: 'CFOP — Código Fiscal de Operações e Prestações',
            html: `
                <p>Código de <strong>4 dígitos</strong> que identifica a natureza de circulação da mercadoria. Instituído pelo <strong>Convênio s/nº de 15/12/1970</strong> e consolidado pelo <strong>Ajuste SINIEF 07/2001</strong>.</p>
                <h6>Significado do 1º dígito</h6>
                <table class="table table-sm small">
                    <tbody>
                        <tr><td><strong>1</strong></td><td>Entrada — operação dentro do Estado</td></tr>
                        <tr><td><strong>2</strong></td><td>Entrada — operação interestadual</td></tr>
                        <tr><td><strong>3</strong></td><td>Entrada — operação com o exterior (importação)</td></tr>
                        <tr><td><strong>5</strong></td><td>Saída — operação dentro do Estado</td></tr>
                        <tr><td><strong>6</strong></td><td>Saída — operação interestadual</td></tr>
                        <tr><td><strong>7</strong></td><td>Saída — operação com o exterior (exportação)</td></tr>
                    </tbody>
                </table>
                <h6 class="mt-3">CFOPs mais usados em saída</h6>
                <table class="table table-sm small table-striped">
                    <thead><tr><th>CFOP</th><th>Descrição (texto oficial)</th></tr></thead>
                    <tbody>
                        <tr><td>5.101</td><td>Venda de produção do estabelecimento</td></tr>
                        <tr><td>5.102</td><td>Venda de mercadoria adquirida ou recebida de terceiros</td></tr>
                        <tr><td>5.103</td><td>Venda de produção do estabelecimento, efetuada fora do estabelecimento</td></tr>
                        <tr><td>5.201</td><td>Devolução de compra para industrialização</td></tr>
                        <tr><td>5.202</td><td>Devolução de compra para comercialização</td></tr>
                        <tr><td>5.401</td><td>Venda de produção com ST — substituto</td></tr>
                        <tr><td>5.403</td><td>Venda de mercadoria de terceiros com ST — substituto</td></tr>
                        <tr><td>5.405</td><td>Venda de mercadoria com ST — substituído (ICMS já retido)</td></tr>
                        <tr><td>5.910</td><td>Remessa em bonificação, doação ou brinde</td></tr>
                        <tr><td>5.911</td><td>Remessa de amostra grátis</td></tr>
                        <tr><td>5.912</td><td>Remessa de mercadoria para demonstração</td></tr>
                        <tr><td>5.949</td><td>Outra saída de mercadoria ou prestação não especificada</td></tr>
                        <tr><td>6.101</td><td>Venda de produção (interestadual)</td></tr>
                        <tr><td>6.102</td><td>Venda de mercadoria de terceiros (interestadual)</td></tr>
                        <tr><td>6.107</td><td>Venda de produção a não contribuinte (interestadual)</td></tr>
                        <tr><td>6.108</td><td>Venda de mercadoria de terceiros a não contribuinte (interestadual)</td></tr>
                        <tr><td>6.404</td><td>Venda de mercadoria com ST já retida (interestadual)</td></tr>
                        <tr><td>7.101</td><td>Venda de produção (exportação)</td></tr>
                        <tr><td>7.102</td><td>Venda de mercadoria de terceiros (exportação)</td></tr>
                    </tbody>
                </table>
                <h6 class="mt-3">Fonte oficial</h6>
                <ul class="small">
                    <li><a href="https://www.confaz.fazenda.gov.br/legislacao/ajustes/2001/AJ_007_01" target="_blank" rel="noopener">Ajuste SINIEF 07/2001</a> (consolidação CFOP)</li>
                </ul>
            `,
        },
        cst: {
            title: 'CST ICMS — Regime Normal (Lucro Real/Presumido)',
            html: `
                <p>Código de Situação Tributária para ICMS, usado por contribuintes do regime Normal. Definido na <strong>Tabela B do Anexo do Convênio s/nº de 15/12/1970</strong>, alterado pelo <strong>Ajuste SINIEF 20/2012</strong>.</p>
                <div class="alert alert-info small mb-2">
                    No XML da NF-e, o CST vai combinado com o código de Origem (Tabela A), por exemplo "000", "010", "060" — mas são campos separados (<code>orig</code> + <code>CST</code>).
                </div>
                <table class="table table-sm small table-striped">
                    <thead><tr><th>CST</th><th>Descrição oficial</th></tr></thead>
                    <tbody>
                        <tr><td><strong>00</strong></td><td>Tributada integralmente</td></tr>
                        <tr><td><strong>10</strong></td><td>Tributada e com cobrança do ICMS por substituição tributária</td></tr>
                        <tr><td><strong>20</strong></td><td>Com redução de base de cálculo</td></tr>
                        <tr><td><strong>30</strong></td><td>Isenta ou não tributada e com cobrança do ICMS por substituição tributária</td></tr>
                        <tr><td><strong>40</strong></td><td>Isenta</td></tr>
                        <tr><td><strong>41</strong></td><td>Não tributada</td></tr>
                        <tr><td><strong>50</strong></td><td>Suspensão</td></tr>
                        <tr><td><strong>51</strong></td><td>Diferimento</td></tr>
                        <tr><td><strong>60</strong></td><td>ICMS cobrado anteriormente por substituição tributária</td></tr>
                        <tr><td><strong>70</strong></td><td>Com redução de base de cálculo e cobrança do ICMS por substituição tributária</td></tr>
                        <tr><td><strong>90</strong></td><td>Outras</td></tr>
                    </tbody>
                </table>
                <div class="alert alert-warning small mt-3">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Se sua empresa é do <strong>Simples Nacional</strong>, use CSOSN (Tabela B do Ajuste SINIEF 03/2010) em vez de CST.
                </div>
                <h6 class="mt-3">Fontes oficiais</h6>
                <ul class="small">
                    <li><a href="https://www.confaz.fazenda.gov.br/legislacao/convenios/1970/CV_SN_70" target="_blank" rel="noopener">Convênio s/nº de 15/12/1970</a> (Anexo — CST)</li>
                    <li><a href="https://www.confaz.fazenda.gov.br/legislacao/ajustes/2012/AJ_020_12" target="_blank" rel="noopener">Ajuste SINIEF 20/2012</a> (Origem — Tabela A)</li>
                </ul>
            `,
        },
        csosn: {
            title: 'CSOSN — Simples Nacional',
            html: `
                <p>Código de Situação da Operação no Simples Nacional — <strong>3 dígitos</strong>, usado em substituição ao CST por contribuintes optantes do Simples. Instituído pelo <strong>Ajuste SINIEF 03/2010</strong>.</p>
                <table class="table table-sm small table-striped">
                    <thead><tr><th>CSOSN</th><th>Descrição oficial</th></tr></thead>
                    <tbody>
                        <tr><td><strong>101</strong></td><td>Tributada pelo Simples Nacional com permissão de crédito</td></tr>
                        <tr><td><strong>102</strong></td><td>Tributada pelo Simples Nacional sem permissão de crédito <span class="badge bg-info">mais comum</span></td></tr>
                        <tr><td><strong>103</strong></td><td>Isenção do ICMS no Simples Nacional para faixa de receita bruta</td></tr>
                        <tr><td><strong>201</strong></td><td>Tributada com permissão de crédito e com cobrança do ICMS por ST</td></tr>
                        <tr><td><strong>202</strong></td><td>Tributada sem permissão de crédito e com cobrança do ICMS por ST</td></tr>
                        <tr><td><strong>203</strong></td><td>Isenção do ICMS para faixa de receita bruta com cobrança do ICMS por ST</td></tr>
                        <tr><td><strong>300</strong></td><td>Imune</td></tr>
                        <tr><td><strong>400</strong></td><td>Não tributada pelo Simples Nacional</td></tr>
                        <tr><td><strong>500</strong></td><td>ICMS cobrado anteriormente por ST (substituído) ou por antecipação <span class="badge bg-info">já retido</span></td></tr>
                        <tr><td><strong>900</strong></td><td>Outros</td></tr>
                    </tbody>
                </table>
                <h6 class="mt-3">Fontes oficiais</h6>
                <ul class="small">
                    <li><a href="https://www.confaz.fazenda.gov.br/legislacao/ajustes/2010/AJ_003_10" target="_blank" rel="noopener">Ajuste SINIEF 03/2010</a> (institui o CSOSN)</li>
                    <li><a href="https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp123.htm" target="_blank" rel="noopener">Lei Complementar 123/2006</a> (Simples Nacional)</li>
                </ul>
            `,
        },
        origem: {
            title: 'Origem da Mercadoria',
            html: `
                <p><strong>1º dígito do CST</strong> — indica a procedência do produto. Tabela A do <strong>Ajuste SINIEF 20/2012</strong>, que criou os códigos 3–8 para implementar a <strong>Resolução SF 13/2012</strong> (alíquota interestadual de 4% para importados).</p>
                <table class="table table-sm small table-striped">
                    <thead><tr><th>Código</th><th>Descrição oficial</th></tr></thead>
                    <tbody>
                        <tr><td><strong>0</strong></td><td>Nacional, exceto as indicadas nos códigos 3, 4, 5 e 8</td></tr>
                        <tr><td><strong>1</strong></td><td>Estrangeira — Importação direta, exceto a indicada no código 6</td></tr>
                        <tr><td><strong>2</strong></td><td>Estrangeira — Adquirida no mercado interno, exceto a indicada no código 7</td></tr>
                        <tr><td><strong>3</strong></td><td>Nacional, com Conteúdo de Importação > 40% e ≤ 70%</td></tr>
                        <tr><td><strong>4</strong></td><td>Nacional, processos produtivos básicos (PPBs — Decreto-Lei 288/67, Leis 8.248/91, 8.387/91, 10.176/01, 11.484/07)</td></tr>
                        <tr><td><strong>5</strong></td><td>Nacional, com Conteúdo de Importação ≤ 40%</td></tr>
                        <tr><td><strong>6</strong></td><td>Estrangeira — Importação direta, sem similar nacional (lista CAMEX) e gás natural</td></tr>
                        <tr><td><strong>7</strong></td><td>Estrangeira — Adquirida no mercado interno, sem similar nacional (lista CAMEX) e gás natural</td></tr>
                        <tr><td><strong>8</strong></td><td>Nacional, com Conteúdo de Importação > 70%</td></tr>
                    </tbody>
                </table>
                <div class="alert alert-warning small mt-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Se a origem for <strong>1, 2, 3, 6, 7 ou 8</strong>, a NF-e exige os campos de <strong>Declaração de Importação (DI)</strong> no grupo de importação (NT 2015.003).
                </div>
                <h6 class="mt-3">Fontes oficiais</h6>
                <ul class="small">
                    <li><a href="https://www.confaz.fazenda.gov.br/legislacao/ajustes/2012/AJ_020_12" target="_blank" rel="noopener">Ajuste SINIEF 20/2012</a></li>
                    <li><a href="https://www25.senado.leg.br/web/atividade/materias/-/materia/105107" target="_blank" rel="noopener">Resolução SF 13/2012</a> (4% interestadual de importados)</li>
                </ul>
            `,
        },
        icms: {
            title: 'ICMS — Alíquotas internas e interestaduais',
            html: `
                <p>Imposto estadual sobre Circulação de Mercadorias e Serviços. Alíquotas definidas por cada UF; valores de referência (2026):</p>
                <h6>Alíquotas internas padrão por UF</h6>
                <div style="max-height: 280px; overflow-y: auto;">
                <table class="table table-sm small table-striped">
                    <thead class="position-sticky top-0 bg-white"><tr><th>UF</th><th>Alíquota padrão</th><th>FCP</th></tr></thead>
                    <tbody>
                        <tr><td>AC</td><td>19%</td><td>até 2%</td></tr>
                        <tr><td>AL</td><td>20% (19% + 1% FECOEP)</td><td>1% FECOEP</td></tr>
                        <tr><td>AM</td><td>20%</td><td>até 2%</td></tr>
                        <tr><td>AP</td><td>18%</td><td>até 2%</td></tr>
                        <tr><td>BA</td><td>20,5%</td><td>até 2%</td></tr>
                        <tr><td>CE</td><td>20%</td><td>até 2%</td></tr>
                        <tr><td>DF</td><td>20%</td><td>até 2%</td></tr>
                        <tr><td>ES</td><td>17%</td><td>até 2%</td></tr>
                        <tr><td>GO</td><td>19%</td><td>até 2%</td></tr>
                        <tr><td>MA</td><td>23%</td><td>até 2%</td></tr>
                        <tr><td>MG</td><td>18%</td><td>até 2%</td></tr>
                        <tr><td>MS</td><td>17%</td><td>até 2%</td></tr>
                        <tr><td>MT</td><td>17%</td><td>até 2%</td></tr>
                        <tr><td>PA</td><td>19%</td><td>até 2%</td></tr>
                        <tr><td>PB</td><td>20%</td><td>até 2%</td></tr>
                        <tr><td>PE</td><td>20,5%</td><td>até 2%</td></tr>
                        <tr><td>PI</td><td>21%</td><td>até 2%</td></tr>
                        <tr><td>PR</td><td>19,5%</td><td>até 2%</td></tr>
                        <tr><td>RJ</td><td>22% (20% + 2% FECP)</td><td>2% FECP</td></tr>
                        <tr><td>RN</td><td>20%</td><td>até 2%</td></tr>
                        <tr><td>RO</td><td>19,5%</td><td>até 2%</td></tr>
                        <tr><td>RR</td><td>20%</td><td>até 2%</td></tr>
                        <tr><td>RS</td><td>17%</td><td>até 2%</td></tr>
                        <tr><td>SC</td><td>17%</td><td>até 2%</td></tr>
                        <tr><td>SE</td><td>22%</td><td>até 2%</td></tr>
                        <tr><td>SP</td><td>18%</td><td>até 2%</td></tr>
                        <tr><td>TO</td><td>20%</td><td>até 2%</td></tr>
                    </tbody>
                </table>
                </div>
                <h6 class="mt-3">Alíquotas interestaduais</h6>
                <table class="table table-sm small">
                    <tbody>
                        <tr><td>S/SE (exceto ES) → N/NE/CO + ES</td><td><strong>7%</strong></td></tr>
                        <tr><td>Demais combinações</td><td><strong>12%</strong></td></tr>
                        <tr><td>Mercadoria importada (qualquer UF)</td><td><strong>4%</strong> (Res. SF 13/2012)</td></tr>
                    </tbody>
                </table>
                <div class="alert alert-warning small mt-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Alíquotas mudam com frequência por legislação estadual. Confirme no <strong>RICMS da UF</strong> antes de emitir.
                </div>
                <h6 class="mt-3">Fontes oficiais</h6>
                <ul class="small">
                    <li><a href="https://www.confaz.fazenda.gov.br/" target="_blank" rel="noopener">CONFAZ</a> — convênios e ajustes</li>
                    <li><a href="https://www25.senado.leg.br/web/atividade/materias/-/materia/1308" target="_blank" rel="noopener">Resolução SF 22/1989</a> — alíquotas interestaduais</li>
                </ul>
            `,
        },
        pis: {
            title: 'PIS — Contribuição para o PIS/PASEP',
            html: `
                <p>Contribuição federal sobre faturamento/receita. Base legal: <strong>Lei 10.637/2002</strong> (não-cumulativo) e <strong>Lei 9.718/1998</strong> (cumulativo).</p>
                <table class="table table-sm small">
                    <thead><tr><th>Regime</th><th>Alíquota PIS</th><th>Créditos?</th></tr></thead>
                    <tbody>
                        <tr><td>Lucro Real (não-cumulativo)</td><td><strong>1,65%</strong></td><td>Sim (insumos, energia, aluguel...)</td></tr>
                        <tr><td>Lucro Presumido / Arbitrado (cumulativo)</td><td><strong>0,65%</strong></td><td>Não</td></tr>
                        <tr><td>Simples Nacional</td><td>Incluso no DAS</td><td>Não destaca na NF-e</td></tr>
                        <tr><td>Monofásico / Alíquota Zero / ST</td><td>Variável</td><td>Conforme lei específica</td></tr>
                    </tbody>
                </table>
                <h6 class="mt-3">CST PIS/COFINS — saídas mais usados</h6>
                <table class="table table-sm small">
                    <tbody>
                        <tr><td>01</td><td>Operação Tributável com Alíquota Básica</td></tr>
                        <tr><td>02</td><td>Operação Tributável com Alíquota Diferenciada</td></tr>
                        <tr><td>04</td><td>Operação Tributável Monofásica — Revenda a Alíquota Zero</td></tr>
                        <tr><td>06</td><td>Operação Tributável a Alíquota Zero</td></tr>
                        <tr><td>07</td><td>Operação Isenta da Contribuição</td></tr>
                        <tr><td>08</td><td>Operação sem Incidência da Contribuição</td></tr>
                        <tr><td>49</td><td>Outras Operações de Saída</td></tr>
                        <tr><td>99</td><td>Outras Operações</td></tr>
                    </tbody>
                </table>
                <h6 class="mt-3">Fontes oficiais</h6>
                <ul class="small">
                    <li><a href="https://www.planalto.gov.br/ccivil_03/leis/2002/l10637.htm" target="_blank" rel="noopener">Lei 10.637/2002</a> (PIS não-cumulativo)</li>
                    <li><a href="https://www.planalto.gov.br/ccivil_03/leis/l9718.htm" target="_blank" rel="noopener">Lei 9.718/1998</a> (cumulativo)</li>
                    <li><a href="https://normas.receita.fazenda.gov.br/sijut2consulta/link.action?idAto=127905" target="_blank" rel="noopener">IN RFB 2.121/2022</a> (consolidação)</li>
                </ul>
            `,
        },
        cofins: {
            title: 'COFINS — Contribuição para Financiamento da Seguridade Social',
            html: `
                <p>Contribuição federal sobre faturamento/receita. Base legal: <strong>Lei 10.833/2003</strong> (não-cumulativo) e <strong>Lei 9.718/1998</strong> (cumulativo).</p>
                <table class="table table-sm small">
                    <thead><tr><th>Regime</th><th>Alíquota COFINS</th><th>Créditos?</th></tr></thead>
                    <tbody>
                        <tr><td>Lucro Real (não-cumulativo)</td><td><strong>7,60%</strong></td><td>Sim</td></tr>
                        <tr><td>Lucro Presumido / Arbitrado (cumulativo)</td><td><strong>3,00%</strong></td><td>Não</td></tr>
                        <tr><td>Simples Nacional</td><td>Incluso no DAS</td><td>Não destaca</td></tr>
                        <tr><td>Monofásico / Alíquota Zero / ST</td><td>Variável</td><td>Lei específica</td></tr>
                    </tbody>
                </table>
                <p class="mt-2 small">O <strong>CST de COFINS</strong> segue a mesma tabela do CST de PIS (01–09, 49, 99 etc.).</p>
                <h6 class="mt-3">Fontes oficiais</h6>
                <ul class="small">
                    <li><a href="https://www.planalto.gov.br/ccivil_03/leis/2003/l10.833.htm" target="_blank" rel="noopener">Lei 10.833/2003</a> (COFINS não-cumulativo)</li>
                    <li><a href="https://normas.receita.fazenda.gov.br/sijut2consulta/link.action?idAto=127905" target="_blank" rel="noopener">IN RFB 2.121/2022</a></li>
                </ul>
            `,
        },
        ipi: {
            title: 'IPI — Imposto sobre Produtos Industrializados',
            html: `
                <p>Imposto federal. Incide na saída de <strong>estabelecimento industrial</strong> ou <strong>equiparado a industrial</strong> (importador, filial atacadista de produto importado).</p>
                <h6>Quando aplica</h6>
                <ul class="small">
                    <li>Transformação, beneficiamento, montagem, acondicionamento, renovação (art. 4º RIPI).</li>
                    <li>Equiparado: importador, arrematante (art. 9º RIPI).</li>
                </ul>
                <h6>Quando NÃO aplica</h6>
                <ul class="small">
                    <li>Comércio varejista puro (revenda sem transformação) → CST IPI 53 ou 99.</li>
                    <li>Produtos NT (Não Tributados) na TIPI.</li>
                    <li>Simples Nacional: recolhido dentro do DAS, sem destaque no XML.</li>
                </ul>
                <h6>CST IPI — saídas</h6>
                <table class="table table-sm small">
                    <tbody>
                        <tr><td>50</td><td>Saída tributada</td></tr>
                        <tr><td>51</td><td>Saída tributada com alíquota zero</td></tr>
                        <tr><td>52</td><td>Saída isenta</td></tr>
                        <tr><td>53</td><td>Saída não-tributada</td></tr>
                        <tr><td>54</td><td>Saída imune</td></tr>
                        <tr><td>55</td><td>Saída com suspensão</td></tr>
                        <tr><td>99</td><td>Outras saídas</td></tr>
                    </tbody>
                </table>
                <h6 class="mt-3">Fontes oficiais</h6>
                <ul class="small">
                    <li><a href="https://www.planalto.gov.br/ccivil_03/_ato2019-2022/2022/decreto/D11158.htm" target="_blank" rel="noopener">TIPI — Decreto 11.158/2022</a> (tabela vigente)</li>
                    <li><a href="https://www.planalto.gov.br/ccivil_03/_ato2007-2010/2010/decreto/d7212.htm" target="_blank" rel="noopener">RIPI — Decreto 7.212/2010</a></li>
                </ul>
            `,
        },
        ibs: {
            title: 'IBS — Imposto sobre Bens e Serviços (Reforma Tributária)',
            html: `
                <p>Tributo estadual+municipal da <strong>Emenda Constitucional 132/2023</strong>, regulamentada pela <strong>LC 214/2025</strong>. Substitui ICMS e ISS gradualmente entre 2026 e 2033.</p>
                <h6>Transição</h6>
                <table class="table table-sm small">
                    <thead><tr><th>Ano</th><th>Alíquota IBS</th><th>Observação</th></tr></thead>
                    <tbody>
                        <tr><td>2026</td><td><strong>0,9%</strong> (teste)</td><td>Cobrança com compensação via PIS/COFINS</td></tr>
                        <tr><td>2027</td><td>Inicial</td><td>CBS começa a substituir PIS/COFINS</td></tr>
                        <tr><td>2029–2032</td><td>Crescente</td><td>Redução gradual do ICMS</td></tr>
                        <tr><td>2033</td><td>Plena</td><td>ICMS/ISS extintos</td></tr>
                    </tbody>
                </table>
                <h6 class="mt-3">Características</h6>
                <ul class="small">
                    <li>Não-cumulativo (permite crédito em toda a cadeia)</li>
                    <li>Alíquota uniforme por UF/município (regra geral)</li>
                    <li>Gerido pelo Comitê Gestor do IBS</li>
                </ul>
                <h6 class="mt-3">Fontes oficiais</h6>
                <ul class="small">
                    <li><a href="https://www.planalto.gov.br/ccivil_03/constituicao/emendas/emc/emc132.htm" target="_blank" rel="noopener">EC 132/2023</a></li>
                    <li><a href="https://www.gov.br/fazenda/pt-br/assuntos/reforma-tributaria" target="_blank" rel="noopener">Portal Fazenda — Reforma Tributária</a></li>
                </ul>
            `,
        },
        cbs: {
            title: 'CBS — Contribuição sobre Bens e Serviços (Reforma Tributária)',
            html: `
                <p>Tributo federal da <strong>EC 132/2023</strong> e <strong>LC 214/2025</strong>. Substitui PIS e COFINS.</p>
                <h6>Transição</h6>
                <table class="table table-sm small">
                    <thead><tr><th>Ano</th><th>Alíquota CBS</th></tr></thead>
                    <tbody>
                        <tr><td>2026</td><td><strong>0,1%</strong> (teste, compensável via PIS/COFINS)</td></tr>
                        <tr><td>2027</td><td>Entra em vigor plena; PIS/COFINS extintos</td></tr>
                    </tbody>
                </table>
                <h6 class="mt-3">Características</h6>
                <ul class="small">
                    <li>Não-cumulativo amplo (crédito em toda a cadeia)</li>
                    <li>Alíquota única federal, uniforme para todos os setores</li>
                    <li>Gerida pela Receita Federal</li>
                </ul>
                <h6 class="mt-3">Fontes oficiais</h6>
                <ul class="small">
                    <li><a href="https://www.planalto.gov.br/ccivil_03/constituicao/emendas/emc/emc132.htm" target="_blank" rel="noopener">EC 132/2023</a></li>
                    <li><a href="https://www.gov.br/receitafederal/pt-br/assuntos/reforma-tributaria" target="_blank" rel="noopener">Receita Federal — Reforma</a></li>
                </ul>
            `,
        },
        is: {
            title: 'IS — Imposto Seletivo (Reforma Tributária)',
            html: `
                <p>Tributo federal da <strong>EC 132/2023</strong> que incidirá sobre produtos nocivos à saúde ou ao meio ambiente.</p>
                <h6>Hipóteses de incidência (previstas)</h6>
                <ul class="small">
                    <li>Cigarros e derivados do tabaco</li>
                    <li>Bebidas alcoólicas e açucaradas</li>
                    <li>Veículos com motor a combustão</li>
                    <li>Embarcações e aeronaves de luxo</li>
                    <li>Bens minerais extraídos (carvão, petróleo)</li>
                </ul>
                <h6>Cronograma</h6>
                <ul class="small">
                    <li><strong>2026:</strong> sem cobrança</li>
                    <li><strong>2027:</strong> entrada em vigor</li>
                </ul>
                <h6 class="mt-3">Fonte oficial</h6>
                <ul class="small">
                    <li><a href="https://www.planalto.gov.br/ccivil_03/constituicao/emendas/emc/emc132.htm" target="_blank" rel="noopener">EC 132/2023</a></li>
                </ul>
            `,
        },
    };

    function open(field) {
        const el = document.getElementById('erpFiscalHelpModal');
        if (!el) {
            console.warn('[ErpFiscalHelp] modal não montado — inclua <x-erp.fiscal-help-modal /> no layout');
            return;
        }
        const entry = CONTENT[field];
        if (!entry) return;
        document.getElementById('erpFiscalHelpTitle').textContent = entry.title;
        document.getElementById('erpFiscalHelpBody').innerHTML = entry.html;
        (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
    }

    window.ErpFiscalHelp = { open };
})();
