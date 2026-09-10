<?php
require __DIR__ . '/database.php';
$pdo = db();
$sizes = $pdo->query('SELECT * FROM sizes WHERE active=1 AND stock>0 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$price = current_price();
$normalPrice = (int) setting('price_cents');
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>406 Montana — Drop 001</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Mono:wght@400;500&family=DM+Sans:wght@400;500;700&family=Inter:wght@400;600;800&display=swap');

        :root {
            --r: #d1272f;
            --o: #f1e8dc;
            --b: #090807;
            --m: #a0978c
        }

        * {
            box-sizing: border-box
        }

        html {
            scroll-behavior: smooth
        }

        body {
            margin: 0;
            background: var(--b);
            color: var(--o);
            font-family: Inter, Arial, sans-serif;
            overflow-x: hidden
        }

        .noise {
            position: fixed;
            z-index: 8;
            inset: 0;
            pointer-events: none;
            opacity: .12;
            background-image: radial-gradient(#fff .6px, transparent .8px);
            background-size: 4px 4px;
            mix-blend-mode: overlay
        }

        .wrap {
            width: min(1360px, 94vw);
            margin: auto
        }

        .nav {
            position: absolute;
            z-index: 3;
            left: 3vw;
            right: 3vw;
            top: 0;
            height: 78px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #ffffff32;
            font: 600 12px 'DM Sans';
            letter-spacing: .05em
        }

        .nav a {
            color: inherit;
            text-decoration: none
        }

        .nav .brand-mark {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 88px;
            height: 54px;
            overflow: hidden;
        }

        .nav .brand-mark img {
            display: block;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .nav .brand-406 img { transform: scale(1.55); }
        .nav .brand-gg img { transform: scale(1.16); mix-blend-mode: screen; }

        .nav .nav-cta {
            color: #fff;
            padding: 10px 13px;
            border: 1px solid #ffffff52
        }

        .hero {
            min-height: 100svh;
            position: relative;
            isolation: isolate;
            display: grid;
            place-items: end start;
            padding: 130px 3vw 54px;
            overflow: hidden
        }

        .hero:before {
            content: '';
            z-index: -2;
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 72% 42%, #6c1018 0, transparent 24%), linear-gradient(120deg, #070605 30%, #1c080a 100%)
        }

        .kicker,
        .label {
            font: 11px 'DM Mono';
            letter-spacing: .18em;
            color: #ef756d
        }

        .hero-copy {
            max-width: 650px;
            z-index: 1
        }

        .hero h1,
        .big {
            font: clamp(70px, 11vw, 184px)/.92 'Bebas Neue', sans-serif;
            letter-spacing: .02em;
            text-transform: uppercase;
            margin: 14px 0 25px
        }

        .hero h1 em {
            color: var(--r);
            font-style: normal
        }

        .hero p {
            max-width: 440px;
            line-height: 1.6;
            color: #d5c9bb
        }

        .button {
            display: inline-block;
            margin-top: 20px;
            padding: 17px 22px;
            background: var(--r);
            color: #fff;
            text-decoration: none;
            font: 500 12px 'DM Mono';
            letter-spacing: .12em;
            transition: .2s
        }

        .button:hover {
            transform: translateY(-4px);
            background: #ec353d
        }

        .product {
            z-index: 0;
            position: absolute;
            inset: 3% 1% 0 auto;
            width: min(60vw, 900px);
            height: 90%;
            object-fit: contain;
            object-position: center;
            filter: drop-shadow(0 45px 38px #000);
            mix-blend-mode: normal;
        }

        .serial {
            position: absolute;
            right: 3vw;
            bottom: 38px;
            writing-mode: vertical-rl;
            font: 10px 'DM Mono';
            letter-spacing: .18em;
            color: #f6eaddaa
        }

        .ticker {
            background: var(--r);
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            padding: 13px 0;
            font: 500 13px 'DM Mono';
            letter-spacing: .13em
        }

        .ticker span {
            display: inline-block;
            animation: slide 22s linear infinite
        }

        @keyframes slide {
            to {
                transform: translateX(-50%)
            }
        }

        .intro {
            padding: 130px 0;
            display: grid;
            grid-template-columns: .7fr 1.3fr;
            gap: 10vw;
            border-bottom: 1px solid #ffffff22
        }

        .intro .big {
            font-size: clamp(58px, 9vw, 135px);
            margin: 0
        }

        .intro p {
            max-width: 580px;
            font-size: clamp(19px, 2vw, 27px);
            line-height: 1.38;
            margin: 0
        }

        .statement {
            padding: 100px 0;
            display: grid;
            grid-template-columns: 1.05fr .95fr;
            gap: 7vw;
            align-items: center
        }

        .mapbox {
            position: relative;
            min-height: 570px
        }

        .mapbox img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            position: absolute;
            inset: 0;
            filter: none
        }

        .pin {
            position: absolute;
            z-index: 1;
            border: 1px solid #e6d7c5;
            padding: 9px;
            background: #080606e8;
            font: 10px 'DM Mono';
            letter-spacing: .1em
        }

        .pin.a {
            left: 33%;
            top: 28%
        }

        .pin.b {
            right: 20%;
            bottom: 31%
        }

        .statement h2 {
            font: clamp(48px, 7vw, 108px)/.92 'Bebas Neue', sans-serif;
            margin: 16px 0;
            text-transform: uppercase
        }

        .statement p {
            line-height: 1.65;
            color: #c5b9aa
        }

        .tracklist {
            margin-top: 28px;
            border-top: 1px solid #ffffff35
        }

        .tracklist div {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #ffffff23;
            font: 12px 'DM Mono';
            letter-spacing: .08em
        }

        .drop {
            background: #e5ddd1;
            color: #120e0d;
            padding: 110px 0
        }

        .drop-grid {
            width: min(1360px, 94vw);
            margin: auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8vw
        }

        .drop h2 {
            font: clamp(56px, 8vw, 130px)/.92 'Bebas Neue', sans-serif;
            margin: 0
        }

        .drop-product-wrap {
            margin: 28px 0 34px;
            min-height: 420px;
            display: grid;
            place-items: center;
            background: linear-gradient(145deg, #d7cec1, #f2ece4);
            overflow: hidden;
        }

        .drop-product {
            width: 100%;
            height: 500px;
            object-fit: contain;
            filter: drop-shadow(0 28px 24px #0005);
        }

        .limited {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 10px 0 6px;
            color: #9e171d;
            font: 700 11px 'DM Mono';
            letter-spacing: .14em;
        }

        .limited:before {
            content: '';
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--r);
            box-shadow: 0 0 0 5px #d1272f18;
        }

        .form {
            display: grid;
            gap: 15px
        }

        .form label {
            font: 10px 'DM Mono';
            letter-spacing: .14em
        }

        .form input {
            width: 100%;
            padding: 12px 0;
            border: 0;
            border-bottom: 1px solid #201a18;
            background: transparent;
            font: 16px Inter;
            outline: 0
        }

        .form input:focus {
            border-color: var(--r)
        }

        button {
            border: 0;
            background: #15100f;
            color: white;
            padding: 17px;
            font: 500 12px 'DM Mono';
            letter-spacing: .12em;
            cursor: pointer
        }

        button:hover {
            background: var(--r)
        }

        #feedback {
            min-height: 30px;
            margin: 0;
            color: #a71d23;
            font: 11px 'DM Mono';
            line-height: 1.5
        }

        .price { font: 42px 'Bebas Neue'; margin: 8px 0 18px; letter-spacing:.04em }
        .old-price { color:#8a8076; text-decoration:line-through; font:14px 'DM Mono'; margin-left:10px }
        .size-select { width:100%; padding:14px; border:1px solid #201a18; background:transparent; font:16px Inter; }
        .measurements { margin-top:22px; padding-top:16px; border-top:1px solid #ffffff35; white-space:pre-line; color:#c5b9aa; line-height:1.6 }
        .fine {
            font-size: 11px;
            line-height: 1.5;
            color: #6d645d
        }

        .foot {
            padding: 25px 3vw;
            display: flex;
            justify-content: space-between;
            color: #91877d;
            font: 500 12px 'DM Sans';
            letter-spacing: .05em
        }

        @media(max-width:760px) {
            .nav span {
                display: none
            }

            .hero {
                padding: 110px 7vw 35px;
                min-height: 860px;
                align-items: start
            }

            .hero h1 {
                font-size: clamp(80px, 23vw, 130px);
                margin-top: 20px
            }

            .hero-copy {
                margin-top: 340px
            }

            .product {
                width: 92vw;
                right: 4vw;
                top: 55px;
                height: 420px;
                object-position: center
            }

            .intro,
            .statement,
            .drop-grid {
                grid-template-columns: 1fr;
                gap: 32px
            }

            .intro,
            .statement {
                padding: 75px 0
            }

            .mapbox {
                min-height: 420px
            }

            .statement h2 {
                font-size: 70px
            }

            .drop {
                padding: 72px 0
            }

            .drop-product-wrap { min-height: 320px; }
            .drop-product { height: 380px; }

            .foot {
                padding: 22px 7vw
            }

            .serial {
                bottom: 20px
            }

        }
    </style>
</head>

<body>
    <div class="noise"></div>
    <section class="hero" id="top">
        <nav class="nav">
            <a class="brand-mark brand-406" href="#top" aria-label="406 REC"><img src="assets/logo406.png" alt="Logo 406"></a>
            <span>DROP 001 — 406 MONTANA</span>
            <a class="brand-mark brand-gg" href="#top" aria-label="GG — designer da camiseta"><img src="assets/GG_v3.png" alt="GG, designer da camiseta"></a>
        </nav>
        <div class="hero-copy">
            <div class="kicker">PRÉ-VENDA LIMITADA / 2026</div>
            <h1>VISTA<br>O <em>CICLO.</em></h1>
            <p>406 Montana não é só uma camiseta. É o primeiro uniforme de quem continua avançando — sem se esquecer de onde veio.</p><a class="button" href="#lista">VER A PEÇA →</a>
        </div><img class="product animate-bounce" src="assets/shirt.png" alt="Camisa 406 Montana com arte de ouroboros">
        <div class="serial">FOUR O SIX / PRE-ORDER 001</div>
    </section>
    <div class="ticker"><span>406 MONTANA ✦ CAMISAS LIMITADAS ✦ OUROBOROS ✦ BELO HORIZONTE ↔ MONTANA ✦ 406 MONTANA ✦
            CAMISAS LIMITADAS ✦ OUROBOROS ✦ BELO HORIZONTE ↔ MONTANA ✦ </span></div>
    <main class="wrap">
        <section class="intro">
            <div>
                <div class="label">O QUE É 406?</div>
                <h2 class="big">NÃO<br>É<br>REPETIÇÃO.</h2>
            </div>
            <p>Ouroboros é voltar ao começo com outra visão. A 406 nasceu entre Belo Horizonte e Montana, nos Estados
                Unidos: dois pontos do mapa conectados por uma mesma missão.</p>
        </section>
        <section class="statement">
            <div class="mapbox"><img src="assets/map.png" alt="Mapa entre Montana e Belo Horizonte"><span
                    class="pin a">MONTANA, USA</span><span class="pin b">BELO HORIZONTE, BR</span></div>
            <div>
                <div class="label">406 MONTANA / O EP</div>
                <h2>DIFERENTE<br>DOS IGUAIS.</h2>
                <p>Um selo. Um coletivo. Uma linguagem que vive no som, na roupa e no movimento. A peça carrega a
                    assinatura visual do EP 406 Montana.</p>
            </div>
        </section>
    </main>
    <section class="drop" id="lista">
        <div class="drop-grid">
            <div>
                <div class="label">DROP 001 / DISPONÍVEL</div>
                <h2><?=htmlspecialchars(setting('product_name'))?></h2>
                <div class="limited">PEÇAS LIMITADAS</div>
                <div class="drop-product-wrap"><img class="drop-product" src="assets/shirt.png" alt="Camisa 406 Montana"></div>
                <p><?=nl2br(htmlspecialchars(setting('product_description')))?></p>
                <div class="measurements"><strong><?=htmlspecialchars(setting('measurements_title'))?></strong><br><?=nl2br(htmlspecialchars(setting('measurements')))?></div>
            </div>
            <form class="form" method="post" action="cart.php">
                <div class="price"><?=money($price)?><?php if ($normalPrice !== $price): ?><span class="old-price"><?=money($normalPrice)?></span><?php endif; ?></div>
                <label>ESCOLHA O TAMANHO<select class="size-select" name="size_id" required><option value="">Selecione</option><?php foreach ($sizes as $size): ?><option value="<?=$size['id']?>"><?=htmlspecialchars($size['name'])?><?= $size['measurements'] ? ' — '.htmlspecialchars($size['measurements']) : '' ?></option><?php endforeach; ?></select></label>
                <?php if (!$sizes): ?><p id="feedback">Os tamanhos serão disponibilizados em breve.</p><?php else: ?><button>ADICIONAR AO CARRINHO</button><?php endif; ?>
                <p class="fine">Peças limitadas. Preço, tamanhos e medidas são atualizados pelo painel da loja.</p>
            </form>
        </div>
    </section>
    <footer class="foot"><span>© 406 REC. 2026</span><span>FOUR O SIX</span></footer>
    
</body>

</html>