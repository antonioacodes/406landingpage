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


        .drop{background:#eee9e1;color:#171411;padding:88px 0;scroll-margin-top:24px}
        .drop-grid{width:min(1180px,90vw);margin:auto;display:grid;grid-template-columns:minmax(0,1.12fr) minmax(0,.88fr);gap:72px;align-items:center}
        .drop-product-wrap{margin:0;display:grid;place-items:center;background:transparent;min-width:0}
        .drop-product{display:block;width:100%;height:auto;max-height:690px;object-fit:contain;filter:none}
        .product-info{min-width:0;padding:16px 0}
        .drop .label{color:#8a534c;font:600 11px/1.5 Inter,Arial,sans-serif;letter-spacing:.16em}
        .drop h2{font:600 clamp(30px,3.4vw,46px)/1.12 Inter,Arial,sans-serif;letter-spacing:-.045em;margin:16px 0}
        .product-description{font:400 14px/1.8 Inter,Arial,sans-serif;color:#6a625a;margin:14px 0 22px}
        .limited{display:inline-flex;align-items:center;gap:8px;font:600 11px/1.4 Inter,Arial,sans-serif;letter-spacing:.07em;color:#8e2b30}
        .limited:before{content:'';width:6px;height:6px;border-radius:50%;background:#a62a32}
        .price{display:flex;flex-wrap:wrap;align-items:baseline;gap:12px;font:600 32px/1.2 Inter,Arial,sans-serif;letter-spacing:-.04em;margin:26px 0}
        .old-price{font:400 15px/1.3 Inter,Arial,sans-serif;color:#91867d;text-decoration:line-through;letter-spacing:0}
        .form{display:grid;gap:16px;align-self:start;align-content:start;grid-auto-rows:max-content;margin:0}
        .form label{display:grid;gap:10px;font:600 12px/1.5 Inter,Arial,sans-serif;letter-spacing:.02em}
        .size-select{display:block;width:100%;height:50px;padding:0 14px;color:#211c18;border:1px solid #c5bdb3;border-radius:4px;background:#f7f3ed;font:400 16px Inter,Arial,sans-serif}
        .size-select:focus-visible{outline:2px solid #a82d36;outline-offset:3px}
        .form button{display:flex;align-items:center;justify-content:space-between;width:100%;height:54px;min-height:54px;padding:0 20px;border:0;border-radius:4px;background:#1b1816;color:#fff;font:600 13px Inter,Arial,sans-serif;letter-spacing:.02em;cursor:pointer;transition:background .2s}
        .form button:hover{background:#a42630}
        .form button span{font-size:20px;font-weight:400}
        .fine{font:400 12px/1.6 Inter,Arial,sans-serif;color:#7c7167;margin:0}
        #feedback{font:400 13px/1.6 Inter,Arial,sans-serif;color:#8e2b30}
        .measurements{margin-top:28px;border-top:1px solid #cec5bb;border-bottom:1px solid #cec5bb;padding:18px 0}
        .measurements summary{cursor:pointer;font:600 13px/1.5 Inter,Arial,sans-serif}
        .measurements-body{font:400 13px/1.8 Inter,Arial,sans-serif;color:#6a625a;margin-top:14px;overflow-wrap:anywhere}
        @media(max-width:760px){.drop{padding:48px 0}.drop-grid{grid-template-columns:1fr;gap:16px;width:88vw}.drop-product{max-height:450px}.product-info{padding:0}.drop h2{font-size:32px}.price{font-size:28px;margin:22px 0}.drop-product-wrap{min-height:0}}

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

            .drop-product-wrap { min-height: 0; }
            .drop-product { height: auto; }

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
            <div class="kicker">DROP LIMITADO / DISPONÍVEL</div>
            <h1>VISTA<br>O <em>CICLO.</em></h1>
            <p>406 Montana não é só uma camiseta. É o primeiro uniforme de quem continua avançando — sem se esquecer de onde veio.</p><a class="button" href="#lista">VER A PEÇA →</a>
        </div><img class="product animate-bounce" src="assets/shirt.png" alt="Camisa 406 Montana com arte de ouroboros">
        <div class="serial">FOUR O SIX / DROP 001</div>
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
    <section class="drop" id="lista" aria-label="Comprar camiseta 406 Montana">
        <div class="drop-grid">
            <div class="drop-product-wrap"><img class="drop-product" src="assets/shirt.png" alt="Camisa 406 Montana com arte de ouroboros" loading="lazy"></div>
            <div class="product-info">
                <div class="label">406 REC × GG / DROP 001</div>
                <h2><?=htmlspecialchars(setting('product_name'))?></h2>
                <div class="limited">Peças limitadas</div>
                <p class="product-description"><?=nl2br(htmlspecialchars(setting('product_description')))?></p>
                <div class="price"><?=money($price)?><?php if ($normalPrice > $price): ?><span class="old-price"><?=money($normalPrice)?></span><?php endif; ?></div>
                <form class="form" method="post" action="cart.php">
                    <label for="product-size">Escolha seu tamanho
                        <select id="product-size" class="size-select" name="size_id" required>
                            <option value="">Selecione o tamanho</option>
                            <?php foreach ($sizes as $size): ?><option value="<?=$size['id']?>"><?=htmlspecialchars($size['name'])?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <?php if (!$sizes): ?><p id="feedback">Os tamanhos serão disponibilizados em breve.</p><?php else: ?><button type="submit">Adicionar ao carrinho <span aria-hidden="true">→</span></button><?php endif; ?>
                    <p class="fine">Uma peça da história entre Belo Horizonte e Montana.</p>
                </form>
                <details class="measurements">
                    <summary><?=htmlspecialchars(setting('measurements_title', 'Guia de medidas'))?></summary>
                    <div class="measurements-body"><?=nl2br(htmlspecialchars(setting('measurements')))?>
                    <?php foreach ($sizes as $size): if ($size['measurements']): ?><p><strong><?=htmlspecialchars($size['name'])?></strong> — <?=htmlspecialchars($size['measurements'])?></p><?php endif; endforeach; ?>
                    </div>
                </details>
            </div>
        </div>
    </section>
    <footer class="foot"><span>© 406 REC. 2026</span><span>FOUR O SIX</span></footer>
    
</body>

</html>