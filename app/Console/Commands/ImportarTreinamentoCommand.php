<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\TreinamentoController;
use App\Models\Treinamento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Publica um vídeo de treinamento a partir de um arquivo que já está no servidor (20/11/2026).
 * Serve para subir um curso inteiro sem passar vídeo por vídeo pelo navegador.
 * Rodar de novo com o mesmo título e a mesma seção não duplica: troca o vídeo.
 *
 *   php artisan treinamento:importar /tmp/aula.mp4 --titulo="1. Configurar a loja" --secao=loja_online --ordem=101
 */
class ImportarTreinamentoCommand extends Command
{
    protected $signature = 'treinamento:importar {arquivo : caminho do vídeo no servidor}
        {--titulo= : título que aparece na lista}
        {--secao=erp : erp | loja_online}
        {--ordem=0 : posição na lista}
        {--descricao= : texto abaixo do título}
        {--perfis= : perfis além de admin/dono/gerente, separados por vírgula (financeiro,vendedor,caixa,consulta)}';

    protected $description = 'Publica um vídeo de treinamento a partir de um arquivo no servidor';

    public function handle(): int
    {
        $origem = (string) $this->argument('arquivo');
        $titulo = trim((string) $this->option('titulo'));
        $secao = (string) $this->option('secao');
        $ext = strtolower(pathinfo($origem, PATHINFO_EXTENSION));

        if (! is_file($origem) || ! is_readable($origem)) {
            $this->error("Arquivo não encontrado: {$origem}");

            return self::FAILURE;
        }
        if ($titulo === '' || ! isset(Treinamento::SECOES[$secao]) || ! in_array($ext, TreinamentoController::EXTENSOES, true)) {
            $this->error('Informe --titulo, uma --secao válida (' . implode(', ', array_keys(Treinamento::SECOES)) . ') e um vídeo ' . implode('/', TreinamentoController::EXTENSOES) . '.');

            return self::FAILURE;
        }
        $validos = array_map(fn ($p) => $p->value, Treinamento::PERFIS);
        $perfis = array_values(array_intersect($validos, array_filter(array_map('trim', explode(',', (string) $this->option('perfis'))))));

        $disco = Storage::disk('local');
        $caminho = TreinamentoController::PASTA . '/' . Str::uuid() . '.' . $ext;
        $disco->makeDirectory(TreinamentoController::PASTA);
        if (! copy($origem, $disco->path($caminho))) {
            $this->error('Não foi possível copiar o vídeo para o armazenamento.');

            return self::FAILURE;
        }

        $t = Treinamento::where('secao', $secao)->where('titulo', $titulo)->first();
        $antigo = $t?->arquivo;
        $dados = [
            'titulo' => $titulo, 'secao' => $secao, 'descricao' => $this->option('descricao') ?: null,
            'ordem' => (int) $this->option('ordem'), 'ativo' => true, 'perfis' => $perfis,
            'arquivo' => $caminho, 'tamanho' => $disco->size($caminho),
        ];
        $t ? $t->update($dados) : $t = Treinamento::create($dados);
        if ($antigo && $antigo !== $caminho) {
            $disco->delete($antigo);
        }

        $this->info("#{$t->id} {$titulo} [" . Treinamento::SECOES[$secao] . '] ' . number_format($dados['tamanho'] / 1048576, 1, ',', '.') . ' MB');

        return self::SUCCESS;
    }
}
