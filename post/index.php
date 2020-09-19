<?php
session_start();
require('../dbconnect.php');
require ('../functions.php');

if (isset($_SESSION['id']) && ($_SESSION['time'] + 3600) > time()) {
    //ログインしている
    $_SESSION['time'] = time();

    $members = $db->prepare('SELECT * FROM members WHERE id=?');
    $members->execute([$_SESSION['id']]);
    $member = $members->fetch();
} else {
    //ログインしていない
    header('Location: login.php');
    exit();
}

//投稿を記録する
if (!empty($_POST)) {
    if ($_POST['message'] != '') {
        $message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, reply_post_id=?, retweet_post_id=?,created=NOW()');
        $message->execute([
            $member['id'],
            $_POST['message'],
            $_POST['reply_post_id'],
            $_POST['retweet_post_id']
        ]);
        if ($_POST['retweet_post_id'] > 0) {
            $retweet = $db->prepare('INSERT INTO retweets SET member_id=?, post_id=?, created_at=NOW()');
            $retweet->execute([
                $member['id'],
                $_POST['retweet_post_id']
            ]);
        }

        header('Location: index.php');
        exit();
    }
}
//投稿を取得する
$page = isset($_REQUEST['page'])?$_REQUEST['page']:'';
if ($page == '') {
    $page = 1;
}
$page = max($page, 1);

//最終ページを取得する
$counts = $db->query('SELECT COUNT(*) AS cnt FROM posts');
$like_cnt = $counts->fetch();
$maxPage = ceil($like_cnt['cnt'] / 5);
$page = min($page, $maxPage);

$start = ($page - 1) * 5;

$posts = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND is_deleted=false ORDER BY p.created DESC
    LIMIT ?, 5');
$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();

//返信の場合
if (isset($_REQUEST['res'])) {
    $response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p 
        WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
    $response->execute([$_REQUEST['res']]);

    $table = $response->fetch();
    $message = '@' . $table['name'] . ' ' . $table['message'];
}

//いいねの処理
if (isset($_GET['post_id']) && isset($_SESSION['id']) && isset($_GET['action'])) {
    if (($_GET['action']) == 'like') {
        $insert = $db->prepare('INSERT INTO likes SET post_id=?, member_id=?, created_at=NOW()');
        $insert->execute([
            $_GET['post_id'],
            $_SESSION['id']
        ]);
    } else {
        $update = $db->prepare('UPDATE likes SET deleted=true WHERE post_id=? AND member_id=?');
        $update->execute([
            $_GET['post_id'],
            $_SESSION['id']
        ]);
    }
}

//リツイートの処理
if (isset($_GET['retweet'])) {
    $retweet = $db->prepare('SELECT m.name, p. * FROM members m, posts p WHERE m.id=p.member_id AND p.id=?');
    $retweet->execute([
        $_GET['retweet']
    ]);
    $table = $retweet->fetch();
    $message = $table['name'] . 'さんの投稿をリツイート' . "\n" . $table['message'];
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>ひとこと掲示板</title>
    <link rel="stylesheet" href="style.css" />
</head>

<body>
    <div id="wrap">
        <div id="head">
            <h1>ひとこと掲示板</h1>
        </div>
        <div id="content">
            <div style="text-align: right"><a href="logout.php">ログアウト</a></div>
            <form action="" method="post">
                <dl>
                    <dt><?php echo h($member['name']); ?>さん、メッセージをどうぞ</dt>
                    <dd>
                        <label>
                            <textarea name="message" cols="50" rows="5"><?php echo h(isset($message)?($message):''); ?></textarea>
                        </label>
                        <input type="hidden" name="reply_post_id" value="<?php echo h(isset($_REQUEST['res'])?$_REQUEST['res']:''); ?>" />
                        <input type="hidden" name="retweet_post_id" value="<?php echo h(isset($_REQUEST['retweet'])?($_REQUEST['retweet']):''); ?>" />
                    </dd>
                </dl>
                <div>
                    <p>
                        <input type="submit" value="投稿する">
                    </p>
                </div>
            </form>

            <?php
            foreach ($posts as $post) :
                //いいねのカウント
                $like_count = $db->prepare('SELECT COUNT(*) AS cnt FROM likes WHERE post_id=? AND member_id=? AND deleted=false');
                $like_count->execute([
                    $post['id'],
                    $_SESSION['id']
                ]);
                $like_cnt = $like_count->fetch();

                //リツイートカウント
                $retweet_count = $db->prepare('SELECT COUNT(*) AS cnt FROM posts WHERE retweet_post_id=?');
                $retweet_count->execute([
                    $post['id']
                ]);
                $retweet_cnt = $retweet_count->fetch();

                //リツイートされているか
                $is_retweet = $db->prepare('SELECT COUNT(*) AS cnt FROM retweets WHERE member_id=? AND post_id=?');
                $is_retweet->execute([
                    $member['id'],
                    $post['id']
                ]);
                $is_retweet_cnt = $is_retweet->fetch();
            ?>
                <div class="msg">
                    <img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
                    <p>
                        <?php echo nl2br(makeLink(h($post['message']))); ?>
                        <span class="name">
                            (<?php echo h($post['name']); ?>)
                        </span>
                        [<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]

                        <?php if (($_SESSION['id']) != ($post['member_id'])) : ?>
                            <!--いいね-->
                            <?php if ($like_cnt['cnt'] > 0) : ?>
                                [<a href="index.php?post_id=<?php echo h($post['id']); ?>&action=dislike">Dislike</a>]
                            <?php else : ?>
                                [<a href="index.php?post_id=<?php echo h($post['id']); ?>&action=like">Like</a>]
                            <?php endif; ?>
                            <!--リツイート-->
                            <?php if ($is_retweet_cnt['cnt'] <= 0) : ?>
                                [<a href="index.php?retweet=<?php echo h($post['id']); ?>">Retweet</a>]
                            <?php endif; ?>
                        <?php else : ?>
                            <?php if ($post['retweet_post_id'] > 0) : ?>
                                [<a href="delete.php?id=<?php echo h($post['id']); ?>">リツイートの取り消し</a>]
                            <?php endif; ?>
                        <?php endif; ?>
                    </p>
                    <p class="day"><a href="view.php?id=<?php echo h($post['id']) ?>">
                            <?php echo h($post['created']); ?></a>
                    </p>

                    <?php
                    if ($post['reply_post_id'] > 0) :
                    ?>
                        <a href="view.php?id=<?php echo h($post['reply_post_id']); ?>">返信元のメッセージ</a>

                    <?php endif; ?>
                    <?php
                    if ($_SESSION['id'] == $post['member_id'] && $post['retweet_post_id'] == 0) :
                    ?>
                        [<a href="delete.php?id=<?php echo h($post['id']); ?>" style="color:#ff3333;">削除</a>]
                    <?php endif; ?>

                    <?php if ($retweet_cnt['cnt'] > 0) : ?>
                        <p><?php echo $retweet_cnt['cnt']; ?>件のリツイート</p>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>

            <ul class="paging">
                <?php
                if ($page > 1) {
                ?>
                    <li><a href="index.php?page=<?php print($page - 1); ?>">前のページへ</a></li>
                <?php
                } else {
                ?>
                    <li>前のページへ</li>
                <?php
                }
                ?>
                <?php
                if ($page < $maxPage) {
                ?>
                    <li><a href="index.php?page=<?php print($page + 1); ?>">次のページへ</a></li>
                <?php
                } else {
                ?>
                    <li>次のページへ</li>
                <?php
                }
                ?>
            </ul>

        </div>
</body>

</html>