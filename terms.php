<?php
// terms.php - 利用規約ページ

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();

$isLoggedIn  = isLoggedIn();
$currentUser = $isLoggedIn ? getCurrentUser() : null;

$UPDATED = '2026年2月16日';
$VERSION = '第1版';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
<title>利用規約 - ミニSNS</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.terms-wrap {
  max-width: 760px;
  margin: 0 auto;
  padding: 40px 24px 80px;
}
.terms-title {
  font-size: 1.7rem;
  font-weight: 800;
  letter-spacing: -.03em;
  margin-bottom: 6px;
  color: var(--text);
}
.terms-meta {
  font-size: .8rem;
  color: var(--text-muted);
  margin-bottom: 36px;
}
.terms-preamble {
  background: var(--bg-card);
  border-left: 3px solid var(--accent);
  border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
  padding: 16px 20px;
  font-size: .9rem;
  color: var(--text-muted);
  line-height: 1.8;
  margin-bottom: 32px;
}
.terms-article {
  margin-bottom: 36px;
}
.terms-article-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text);
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 14px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.terms-article-num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  background: var(--accent);
  color: #fff;
  border-radius: 6px;
  font-size: .78rem;
  font-weight: 700;
  flex-shrink: 0;
}
.terms-body {
  font-size: .9rem;
  color: var(--text-muted);
  line-height: 1.9;
}
.terms-list {
  list-style: none;
  padding: 0;
  margin: 8px 0 0;
}
.terms-list li {
  font-size: .88rem;
  color: var(--text-muted);
  line-height: 1.8;
  padding: 4px 0 4px 20px;
  position: relative;
}
.terms-list li::before {
  content: '・';
  position: absolute;
  left: 4px;
  color: var(--accent);
}
.terms-list.numbered {
  counter-reset: item;
}
.terms-list.numbered li {
  counter-increment: item;
  padding-left: 28px;
}
.terms-list.numbered li::before {
  content: counter(item) '.';
  font-weight: 600;
  color: var(--accent);
}
.terms-indent {
  padding-left: 20px;
  font-size: .83rem;
  color: var(--text-faint);
  line-height: 1.8;
  margin-top: 4px;
}
.terms-footer {
  margin-top: 48px;
  padding-top: 24px;
  border-top: 1px solid var(--border);
  text-align: right;
  font-size: .82rem;
  color: var(--text-muted);
  line-height: 2;
}
.terms-nav {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 32px;
  padding-bottom: 20px;
  border-bottom: 1px solid var(--border);
}
</style>
</head>
<body>

<div class="terms-wrap">

  <!-- ナビ -->
  <div class="terms-nav">
    <a href="index.php" style="color:var(--text-muted);font-size:.9rem">← ホームへ戻る</a>
  </div>

  <div class="terms-title">利用規約</div>
  <div class="terms-meta">制定日：<?= e($UPDATED) ?>　<?= e($VERSION) ?></div>

  <div class="terms-preamble">
    本利用規約（以下「本規約」）は、管理者 <strong style="color:var(--accent)">yamachin</strong>
    が運営するクローズドSNS「ミニSNS」（以下「本サービス」）の利用条件を定めるものです。
    本サービスを利用するすべての方（以下「ユーザー」）は、本規約に同意したものとみなします。
  </div>

  <!-- 第1条 -->
  <div class="terms-article">
    <div class="terms-article-title">
      <span class="terms-article-num">1</span>サービスの性質
    </div>
    <p class="terms-body">本サービスは、管理者が個人として運営する招待制・クローズド型のSNSです。以下の特徴を有します。</p>
    <ul class="terms-list">
      <li>参加は管理者による承認または招待を受けた方に限られます</li>
      <li>営利目的ではなく、個人間のコミュニケーションを目的とします</li>
      <li>日本語を主な使用言語とします</li>
      <li>利用は無償です</li>
    </ul>
  </div>

  <!-- 第2条 -->
  <div class="terms-article">
    <div class="terms-article-title">
      <span class="terms-article-num">2</span>アカウント
    </div>
    <ul class="terms-list numbered">
      <li>1端末につき1アカウントの登録を原則とします。複数アカウントの作成は禁止します。</li>
      <li>ユーザーIDおよびパスワードは自己の責任において管理してください。第三者への譲渡・貸与は禁止します。</li>
      <li>登録情報に変更が生じた場合、速やかにプロフィール情報を更新してください。</li>
      <li>アカウントの不正利用が判明した場合、管理者はアカウントを停止・削除できます。</li>
    </ul>
  </div>

  <!-- 第3条 -->
  <div class="terms-article">
    <div class="terms-article-title">
      <span class="terms-article-num">3</span>年齢制限
    </div>
    <p class="terms-body">
      本サービスの利用に年齢制限は設けていませんが、未成年のユーザーは保護者の同意を得たうえで利用してください。
      管理者は未成年の利用に起因するトラブルについて責任を負いません。
    </p>
  </div>

  <!-- 第4条 -->
  <div class="terms-article">
    <div class="terms-article-title">
      <span class="terms-article-num">4</span>禁止事項
    </div>
    <p class="terms-body">ユーザーは以下の行為を行ってはなりません。</p>
    <ul class="terms-list">
      <li>他のユーザーへの誹謗中傷・ハラスメント・脅迫</li>
      <li>個人情報（本名・住所・電話番号等）の無断掲載</li>
      <li>わいせつ・暴力的・差別的なコンテンツの投稿</li>
      <li>著作権・肖像権・その他知的財産権を侵害する行為</li>
      <li>スパム投稿・広告・勧誘行為</li>
      <li>本サービスへの不正アクセス・改ざん・過負荷をかける行為</li>
      <li>複数アカウントの作成・取得</li>
      <li>法令または公序良俗に反する行為</li>
      <li>管理者または他のユーザーに不利益・損害を与える行為</li>
      <li>その他、管理者が不適切と判断する行為</li>
    </ul>
  </div>

  <!-- 第5条 -->
  <div class="terms-article">
    <div class="terms-article-title">
      <span class="terms-article-num">5</span>投稿コンテンツ
    </div>
    <ul class="terms-list numbered">
      <li>ユーザーが投稿したコンテンツの著作権はユーザー自身に帰属します。</li>
      <li>ユーザーは投稿にあたり、当該コンテンツが第三者の権利を侵害しないことを保証します。</li>
      <li>管理者は、本規約違反または不適切と判断したコンテンツを予告なく削除できます。</li>
      <li>ユーザーは管理者に対し、サービス運営に必要な範囲でコンテンツを利用する権利を無償で許諾するものとします。</li>
    </ul>
  </div>

  <!-- 第6条 -->
  <div class="terms-article">
    <div class="terms-article-title">
      <span class="terms-article-num">6</span>ダイレクトメッセージ
    </div>
    <p class="terms-body">
      ユーザー間のダイレクトメッセージ（DM）には第4条の禁止事項が適用されます。
      管理者はDMの内容を原則として閲覧しませんが、違反行為の調査に必要な場合はこの限りではありません。
    </p>
  </div>

  <!-- 第7条 -->
  <div class="terms-article">
    <div class="terms-article-title">
      <span class="terms-article-num">7</span>個人情報の取り扱い
    </div>
    <ul class="terms-list numbered">
      <li>管理者は、ユーザーから取得した個人情報を本サービスの運営目的にのみ使用します。</li>
      <li>取得する情報は以下のとおりです。
        <div class="terms-indent">
          ・ユーザーID・表示名・自己紹介文<br>
          ・端末識別情報（フィンガープリント）<br>
          ・投稿・いいね・コメント・DMの内容<br>
          ・アクセスログ
        </div>
      </li>
      <li>管理者は法令に基づく場合を除き、個人情報を第三者に提供しません。</li>
      <li>個人情報の開示・訂正・削除を希望する場合は管理者にご連絡ください。</li>
    </ul>
  </div>

  <!-- 第8条 -->
  <div class="terms-article">
    <div class="terms-article-title">
      <span class="terms-article-num">8</span>免責事項
    </div>
    <ul class="terms-list numbered">
      <li>管理者は本サービスを現状有姿で提供します。サービスの継続・無中断・無エラーを保証しません。</li>
      <li>ユーザー間のトラブルについて、管理者は一切の責任を負いません。</li>
      <li>本サービスの利用により生じた損害について、管理者の故意または重大な過失による場合を除き、管理者は責任を負いません。</li>
      <li>管理者は予告なくサービスの内容変更・一時停止・終了を行う場合があります。</li>
    </ul>
  </div>

  <!-- 第9条 -->
  <div class="terms-article">
    <div class="terms-article-title">
      <span class="terms-article-num">9</span>アカウントの停止・削除
    </div>
    <p class="terms-body">管理者は以下の場合にユーザーのアカウントを停止または削除できます。</p>
    <ul class="terms-list">
      <li>本規約への違反が認められる場合</li>
      <li>他のユーザーや第三者から重大な苦情があった場合</li>
      <li>長期間（180日以上）利用がない場合</li>
      <li>その他、管理者がサービス運営上必要と判断した場合</li>
    </ul>
    <p class="terms-body" style="margin-top:10px">
      停止・削除にあたって事前通知は必須としませんが、可能な限り通知するよう努めます。
    </p>
  </div>

  <!-- 第10条 -->
  <div class="terms-article">
    <div class="terms-article-title">
      <span class="terms-article-num">10</span>規約の変更
    </div>
    <p class="terms-body">
      管理者は必要に応じて本規約を変更することがあります。
      変更後も本サービスを継続して利用した場合、変更後の規約に同意したものとみなします。
      重要な変更がある場合は、本サービス内でお知らせします。
    </p>
  </div>

  <!-- 第11条 -->
  <div class="terms-article">
    <div class="terms-article-title">
      <span class="terms-article-num">11</span>準拠法・管轄
    </div>
    <p class="terms-body">
      本規約は日本法に準拠します。本規約に関する紛争については、
      管理者の住所地を管轄する裁判所を第一審の専属的合意管轄裁判所とします。
    </p>
  </div>

  <!-- フッター -->
  <div class="terms-footer">
    制定日：<?= e($UPDATED) ?><br>
    管理者：yamachin
  </div>

</div>

</body>
</html>
