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

//いいねのカウント
function select_like_cnt($db, $post_id, $member_id) {
    $like_count = $db->prepare('SELECT COUNT(*) AS cnt FROM likes WHERE post_id=? AND member_id=? AND deleted=false');
    $like_count->execute([$post_id,$member_id]);
    return $like_count->fetch();
}
//リツイートのカウント
function select_retweet_cnt($db, $id) {
    $retweet_count = $db->prepare('SELECT COUNT(*) AS cnt FROM posts WHERE retweet_post_id=?');
    $retweet_count->execute([$id]);
    return $retweet_count->fetch();
}
//リツイートされているか
function select_is_retweet ($db, $member_id, $post_id) {
    $is_retweet = $db->prepare('SELECT COUNT(*) AS cnt FROM retweets WHERE member_id=? AND post_id=?');
    $is_retweet->execute([$member_id, $post_id]);
    return $is_retweet->fetch();
}