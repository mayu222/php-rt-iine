<?php
//htmlspecialcharsのショートカット
function h($value)
{
    return htmlspecialchars($value,ENT_QUOTES);
}
//本文内のURLにリンクを設定
function makeLink($value)
{
    return mb_ereg_replace("(https?)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)", '<a href="\1\2">\1\2</a>', $value);
}