<?php

function setor_inscritos(mysqli $conn, string $setor): array
{
    $positivo = "LOWER(TRIM(COALESCE(%s, ''))) IN ('sim', 's', 'yes', 'y', 'true', '1')";
    $preenchido = "NULLIF(TRIM(COALESCE(%s, '')), '') IS NOT NULL";

    if ($setor === 'enfermagem') {
        $condicao = sprintf($positivo, 'medicamento_controlado')
            . ' OR ' . sprintf($positivo, 'alergia')
            . ' OR ' . sprintf($preenchido, 'descricao_alergia')
            . ' OR ' . sprintf($positivo, 'restricao_alimentar')
            . ' OR ' . sprintf($preenchido, 'descricao_alimentar');
        $sql = "SELECT id, nome, data_nascimento, medicamento_controlado,
                       restricao_alimentar, descricao_alimentar, alergia,
                       descricao_alergia, nome_contato_emergencia, contato_emergencia
                FROM inscricoes WHERE ($condicao) ORDER BY nome ASC";
    } elseif ($setor === 'cozinha') {
        $condicao = sprintf($positivo, 'restricao_alimentar')
            . ' OR ' . sprintf($preenchido, 'descricao_alimentar');
        $sql = "SELECT id, nome, restricao_alimentar, descricao_alimentar
                FROM inscricoes WHERE ($condicao) ORDER BY nome ASC";
    } else {
        throw new InvalidArgumentException('Setor de relatório inválido.');
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $inscritos = [];
    while ($row = $result->fetch_assoc()) {
        $inscritos[] = $row;
    }
    $stmt->close();
    return $inscritos;
}

function setor_flag_ativa(?string $valor): bool
{
    return in_array(strtolower(trim((string) $valor)), ['sim', 's', 'yes', 'y', 'true', '1'], true);
}

function setor_descricao(?string $indicador, ?string $descricao): string
{
    $descricao = trim((string) $descricao);
    if ($descricao !== '') {
        return $descricao;
    }
    return setor_flag_ativa($indicador) ? 'Sim (descrição não informada)' : '—';
}

function setor_data_nascimento(?string $nascimento): string
{
    $nascimento = trim((string) $nascimento);
    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $nascimento);
    if (!$data || $data->format('Y-m-d') !== $nascimento) {
        return $nascimento !== '' ? $nascimento : '—';
    }
    $idade = $data->diff(new DateTimeImmutable('today'))->y;
    return $data->format('d/m/Y') . ' (' . $idade . ' anos)';
}

function setor_pdf_encode(string $text): string
{
    if (function_exists('iconv')) {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
    } else {
        $encoded = false;
    }
    if ($encoded === false) {
        $encoded = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
    }
    return $encoded;
}

function setor_pdf_escape(string $encoded): string
{
    $encoded = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', $encoded) ?? '';
    return strtr($encoded, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
}

function setor_pdf_text(string $text): string
{
    return setor_pdf_escape(setor_pdf_encode($text));
}

function setor_pdf_text_command(string $text, string $font, int $size, int $x, int $y, bool $alreadyEncoded = false): string
{
    return 'BT /' . $font . ' ' . $size . ' Tf 1 0 0 1 ' . $x . ' ' . $y
        . ' Tm (' . ($alreadyEncoded ? setor_pdf_escape($text) : setor_pdf_text($text)) . ") Tj ET\n";
}

function setor_pdf_bytes(string $setor, string $sincronizadoEm, array $registros): string
{
    $linhas = [];
    foreach ($registros as $indice => $registro) {
        $nome = trim((string) ($registro['nome'] ?? '')) ?: 'Nome não informado';
        $linhas[] = ['text' => setor_pdf_encode(($indice + 1) . '. ' . $nome), 'bold' => true];
        foreach ($registro['campos'] as $campo) {
            $campoCodificado = setor_pdf_encode((string) $campo);
            foreach (explode("\n", wordwrap($campoCodificado, 88, "\n", true)) as $linha) {
                $linhas[] = ['text' => $linha, 'bold' => false];
            }
        }
        $linhas[] = ['text' => '', 'bold' => false];
    }
    if (!$linhas) {
        $linhas[] = ['text' => setor_pdf_encode('Nenhum inscrito corresponde aos critérios deste relatório.'), 'bold' => false];
    }

    $paginas = [];
    $paginaAtual = [];
    foreach ($linhas as $linha) {
        if (count($paginaAtual) >= 48) {
            $paginas[] = $paginaAtual;
            $paginaAtual = [];
        }
        $paginaAtual[] = $linha;
    }
    if ($paginaAtual || !$paginas) {
        $paginas[] = $paginaAtual;
    }

    $totalPaginas = count($paginas);
    $objetos = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [' . implode(' ', array_map(
            static function (int $index): string {
                return (3 + $index * 2) . ' 0 R';
            },
            array_keys($paginas)
        )) . '] /Count ' . $totalPaginas . ' >>'
    ];

    foreach ($paginas as $indice => $linhasPagina) {
        $numeroPagina = $indice + 1;
        $pageObject = 3 + $indice * 2;
        $contentObject = $pageObject + 1;
        $content = setor_pdf_text_command('Acampamento "No Limite com Cristo"', 'F1', 9, 45, 803);
        $content .= setor_pdf_text_command('Relatório do setor: ' . $setor, 'F2', 16, 45, 778);
        $content .= setor_pdf_text_command('Sincronização: ' . $sincronizadoEm, 'F1', 9, 45, 757);
        $content .= setor_pdf_text_command('Total de inscritos filtrados: ' . count($registros), 'F1', 9, 45, 742);
        $content .= '0.72 w 45 730 m 550 730 l S' . "\n";

        $y = 711;
        foreach ($linhasPagina as $linha) {
            if ($linha['text'] !== '') {
                $content .= setor_pdf_text_command(
                    $linha['text'],
                    $linha['bold'] ? 'F2' : 'F1',
                    9,
                    45,
                    $y,
                    true
                );
            }
            $y -= 13;
        }
        $content .= setor_pdf_text_command('Página ' . $numeroPagina . ' de ' . $totalPaginas, 'F1', 8, 490, 28);

        $objetos[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842]'
            . ' /Resources << /Font << /F1 ' . (3 + $totalPaginas * 2) . ' 0 R /F2 '
            . (4 + $totalPaginas * 2) . ' 0 R >> >> /Contents ' . $contentObject . ' 0 R >>';
        $objetos[$contentObject] = '<< /Length ' . strlen($content) . " >>\nstream\n"
            . $content . "endstream";
    }

    $fontRegular = 3 + $totalPaginas * 2;
    $fontBold = $fontRegular + 1;
    $objetos[$fontRegular] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $objetos[$fontBold] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    ksort($objetos);

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    foreach ($objetos as $numero => $objeto) {
        $offsets[$numero] = strlen($pdf);
        $pdf .= $numero . " 0 obj\n" . $objeto . "\nendobj\n";
    }
    $xrefOffset = strlen($pdf);
    $maxObject = max(array_keys($objetos));
    $pdf .= "xref\n0 " . ($maxObject + 1) . "\n0000000000 65535 f \n";
    for ($numero = 1; $numero <= $maxObject; $numero++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$numero]);
    }
    $pdf .= 'trailer << /Size ' . ($maxObject + 1) . " /Root 1 0 R >>\n"
        . "startxref\n" . $xrefOffset . "\n%%EOF\n";
    return $pdf;
}
