<?php


function replacePlaceholder($text)
{
    $search = [
        '{left}',
        '{right}',
    ];
    
    $replace = [
        '‮',
        '‭',
    ];
    
    return str_replace($search, $replace, $text);
}

function loadAndProcessMultilangData(string $multilangFile): string
    {
        if (!file_exists($multilangFile)) {
            throw new Exception("File multibahasa '{$multilangFile}' tidak ditemukan.");
        }
        $jsonContent = file_get_contents($multilangFile);
        $data = json_decode($jsonContent, true);
        if (!is_array($data)) {
            throw new Exception("Format file '{$multilangFile}' tidak valid.");
        }
        $hasil = '';
        foreach ($data as $kodeBahasa => $isi) {
            if (empty($isi['text'])) continue;
            $teks = $isi['text'];
            $kataList = [];
            if ($kodeBahasa === 'zh' || $kodeBahasa === 'ja') {
                $kataList = preg_split('//u', $teks, -1, PREG_SPLIT_NO_EMPTY);
            } elseif ($kodeBahasa === 'ar') {
                preg_match_all('/\p{Arabic}+/u', $teks, $matches);
                $kataList = $matches[0];
            } else {
                preg_match_all('/\b\p{L}+\b/u', $teks, $matches);
                $kataList = $matches[0];
            }
            if (!empty($kataList)) {
                $hasil .= $kataList[array_rand($kataList)] . ' ';
            }
        }
        return trim($hasil);
    }

function hideWordInHtml($html, $words, $placeholder = '##enc##')
    {
        if (!is_array($words)) {
            $words = [$words];
        }
        if (empty($words)) {
            return $html;
        }
        $hidden = '';
        foreach ($words as $word) {
            $hidden .= '<span style="font-size:0;">' . htmlspecialchars($word, ENT_QUOTES | ENT_HTML5) . '</span>';
        }
        return str_replace($placeholder, $hidden, $html);
    }

function undetect($html)
    {
        $search = array('/\>[^\S ]+/s', '/[^\S ]+\</s', '/(\s)+/s');
        $replace = array('>', '<', '\\1');
        $minify = preg_replace($search, $replace, $html);
        $undetect = preg_replace('/<div/', '<div', $minify);
        $undetect = preg_replace('/<\/div/', '</div', $undetect);
        $undetect = preg_replace('/class=\"/', 'class="' . microtime(true) . ' ', $undetect);
        return $undetect;
    }
