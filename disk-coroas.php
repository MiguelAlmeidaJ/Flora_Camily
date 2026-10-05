<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$pageTitle = 'Disk Coroas | Coroa de Flores com Entrega | Flora Camily';
$pageDescription = 'Disk coroas da Flora Camily: escolha sua coroa de flores, personalize a mensagem da faixa e fale direto com nossa equipe pelo WhatsApp para organizar a homenagem e a entrega.';
$canonicalUrl = absoluteUrl('/disk-coroas');
$seoType = 'website';

$whatsappUrl = 'https://wa.me/5532984075039?text=Ol%C3%A1%21%20Gostaria%20de%20comprar%20uma%20homenagem%20floral%20na%20Flora%20Camily.';

$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => 'Disk Coroas | Flora Camily',
        'url' => absoluteUrl('/disk-coroas'),
        'description' => $pageDescription,
        'inLanguage' => 'pt-BR',
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => SITE_NAME,
            'url' => absoluteUrl('/'),
        ],
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        'name' => 'Disk Coroas - Coroas de Flores',
        'serviceType' => 'Coroas de flores e homenagens florais',
        'provider' => [
            '@type' => 'Organization',
            'name' => SITE_NAME,
            'url' => absoluteUrl('/'),
        ],
        'url' => absoluteUrl('/disk-coroas'),
        'description' => 'Atendimento para escolha, personalização e organização da entrega de coroas de flores e homenagens florais.',
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Início',
                'item' => absoluteUrl('/'),
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => 'Disk Coroas',
                'item' => absoluteUrl('/disk-coroas'),
            ],
        ],
    ],
];

require __DIR__ . '/includes/header.php';
?>

<style>
.disk-coroas-page {
    background: #fff;
}
.disk-coroas-hero {
    position: relative;
    overflow: hidden;
    padding: clamp(4rem, 8vw, 7rem) 0;
    background:
        radial-gradient(circle at 85% 18%, rgba(216,161,141,.20), transparent 28%),
        linear-gradient(135deg, #fffaf5 0%, #f7f0e7 100%);
}
.disk-coroas-hero::after {
    content: '';
    position: absolute;
    width: 340px;
    height: 340px;
    right: -120px;
    bottom: -150px;
    border: 1px solid rgba(180,139,86,.22);
    border-radius: 50%;
}
.disk-coroas-copy {
    position: relative;
    z-index: 2;
    max-width: 760px;
}
.disk-coroas-copy h1 {
    margin: 1rem 0 1.25rem;
    color: var(--flora-olive);
    font-size: clamp(3rem, 6.5vw, 5.4rem);
    line-height: .95;
    letter-spacing: -.035em;
}
.disk-coroas-copy .lead {
    max-width: 700px;
    color: #70695f;
    font-size: clamp(1rem, 2vw, 1.15rem);
    line-height: 1.75;
}
.disk-coroas-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .85rem;
    margin-top: 1.8rem;
}
.disk-coroas-actions .btn {
    min-height: 54px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.disk-coroas-trust {
    margin-top: 2rem;
    display: flex;
    flex-wrap: wrap;
    gap: 1rem 1.4rem;
    color: #625d55;
    font-size: .9rem;
    font-weight: 700;
}
.disk-coroas-trust span {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
}
.disk-coroas-trust i {
    color: var(--flora-terracotta);
}
.disk-section {
    padding: clamp(3.5rem, 7vw, 6rem) 0;
}
.disk-section-soft {
    background: var(--flora-cream-2);
}
.disk-section-head {
    max-width: 760px;
    margin-bottom: 2.2rem;
}
.disk-section h2 {
    color: var(--flora-olive);
    font-size: clamp(2.2rem, 4.5vw, 3.7rem);
    line-height: 1.05;
}
.disk-section-head p,
.disk-text {
    color: var(--flora-muted);
    line-height: 1.75;
}
.disk-card {
    height: 100%;
    padding: 1.6rem;
    border: 1px solid var(--flora-border);
    border-radius: 22px;
    background: #fff;
}
.disk-card-icon {
    width: 48px;
    height: 48px;
    display: grid;
    place-items: center;
    margin-bottom: 1rem;
    border-radius: 15px;
    color: var(--flora-olive);
    background: var(--flora-cream);
    font-size: 1.25rem;
}
.disk-card h3 {
    margin-bottom: .65rem;
    color: var(--flora-olive);
    font-size: 1.45rem;
}
.disk-card p {
    margin: 0;
    color: var(--flora-muted);
    line-height: 1.65;
}
.disk-steps {
    counter-reset: diskStep;
}
.disk-step {
    position: relative;
    height: 100%;
    padding: 1.6rem 1.5rem 1.5rem;
    border-top: 1px solid var(--flora-border);
}
.disk-step::before {
    counter-increment: diskStep;
    content: '0' counter(diskStep);
    display: block;
    margin-bottom: .9rem;
    color: var(--flora-terracotta);
    font-weight: 800;
    letter-spacing: .1em;
}
.disk-step h3 {
    color: var(--flora-olive);
    font-size: 1.55rem;
}
.disk-step p {
    color: var(--flora-muted);
    line-height: 1.65;
}
.disk-faq {
    border-top: 1px solid var(--flora-border);
}
.disk-faq details {
    padding: 1.15rem 0;
    border-bottom: 1px solid var(--flora-border);
}
.disk-faq summary {
    cursor: pointer;
    color: var(--flora-olive);
    font-weight: 800;
}
.disk-faq p {
    margin: .8rem 0 0;
    color: var(--flora-muted);
    line-height: 1.7;
}
.disk-cta {
    padding: clamp(2rem, 5vw, 3.2rem);
    border-radius: 28px;
    background: var(--flora-olive);
    color: #fff;
}
.disk-cta h2 {
    color: #fff;
    margin-bottom: .8rem;
}
.disk-cta p {
    max-width: 720px;
    color: rgba(255,255,255,.78);
    line-height: 1.7;
}
.disk-cta .btn {
    min-height: 54px;
}
@media (max-width: 575.98px) {
    .disk-coroas-actions .btn {
        width: 100%;
    }
    .disk-coroas-trust {
        display: grid;
    }
}
</style>

<div class="disk-coroas-page">
    <section class="disk-coroas-hero">
        <div class="container">
            <div class="disk-coroas-copy">
                <span class="eyebrow">
                    <span class="eyebrow-dot"></span>
                    Disk Coroas Flora Camily
                </span>

                <h1>Coroa de flores com atendimento cuidadoso do pedido à entrega.</h1>

                <p class="lead">
                    Em momentos de despedida, cada detalhe importa. Na Flora Camily, você pode escolher
                    uma coroa de flores, personalizar a mensagem da faixa e organizar a homenagem
                    diretamente com nossa equipe pelo WhatsApp.
                </p>

                <div class="disk-coroas-actions">
                    <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener" class="btn btn-brand btn-lg px-4">
                        <i class="bi bi-whatsapp me-2"></i>
                        Pedir coroa de flores
                    </a>
                    <a href="loja.php" class="btn btn-outline-brand btn-lg px-4">
                        Ver opções disponíveis
                    </a>
                </div>

                <div class="disk-coroas-trust" aria-label="Diferenciais do atendimento">
                    <span><i class="bi bi-chat-heart"></i> Atendimento humano</span>
                    <span><i class="bi bi-card-text"></i> Mensagem de faixa personalizada</span>
                    <span><i class="bi bi-truck"></i> Entrega acompanhada</span>
                </div>
            </div>
        </div>
    </section>

    <section class="disk-section">
        <div class="container">
            <div class="disk-section-head">
                <span class="eyebrow">Homenagem floral</span>
                <h2>Disk coroas para tornar a escolha mais simples em um momento delicado.</h2>
                <p>
                    Nossa equipe ajuda você a organizar a homenagem com clareza: escolha da composição,
                    texto da faixa, informações do destino e detalhes de entrega. O atendimento é feito
                    de forma direta e respeitosa.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <article class="disk-card">
                        <div class="disk-card-icon"><i class="bi bi-flower1"></i></div>
                        <h3>Coroas de flores</h3>
                        <p>
                            Opções de homenagens florais preparadas com cuidado para despedidas,
                            velórios, cerimônias e momentos de condolências.
                        </p>
                    </article>
                </div>
                <div class="col-md-4">
                    <article class="disk-card">
                        <div class="disk-card-icon"><i class="bi bi-card-text"></i></div>
                        <h3>Faixa personalizada</h3>
                        <p>
                            Você informa a mensagem desejada e nossa equipe orienta os detalhes
                            para que a homenagem seja entregue com a identificação correta.
                        </p>
                    </article>
                </div>
                <div class="col-md-4">
                    <article class="disk-card">
                        <div class="disk-card-icon"><i class="bi bi-whatsapp"></i></div>
                        <h3>Pedido pelo WhatsApp</h3>
                        <p>
                            Fale diretamente com a Flora Camily para consultar opções, disponibilidade
                            e alinhar os dados necessários para a entrega da coroa de flores.
                        </p>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <section class="disk-section disk-section-soft">
        <div class="container">
            <div class="disk-section-head">
                <span class="eyebrow">Como pedir</span>
                <h2>Como funciona o Disk Coroas da Flora Camily</h2>
                <p>
                    Um processo simples para você resolver a homenagem com segurança e sem complicação.
                </p>
            </div>

            <div class="row g-4 disk-steps">
                <div class="col-md-4">
                    <div class="disk-step">
                        <h3>Fale com a equipe</h3>
                        <p>Abra o WhatsApp e informe que deseja comprar uma homenagem floral.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="disk-step">
                        <h3>Escolha e personalize</h3>
                        <p>Defina a coroa de flores e envie a mensagem que deverá constar na faixa.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="disk-step">
                        <h3>Confirme a entrega</h3>
                        <p>Informe local, data, horário e demais detalhes para a equipe organizar o pedido.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="disk-section">
        <div class="container">
            <div class="row g-5 align-items-start">
                <div class="col-lg-6">
                    <div class="disk-section-head mb-0">
                        <span class="eyebrow">Atendimento rápido</span>
                        <h2>Precisa comprar uma coroa de flores?</h2>
                        <p>
                            O canal de atendimento da Flora Camily foi pensado para facilitar esse momento.
                            Pelo WhatsApp você consegue consultar as homenagens disponíveis e alinhar a
                            personalização e a entrega com nossa equipe.
                        </p>
                        <p class="disk-text mb-0">
                            Se você pesquisou por <strong>disk coroas</strong>, <strong>coroa de flores</strong>,
                            <strong>comprar coroa de flores</strong> ou <strong>entrega de coroa de flores</strong>,
                            este é o canal direto para solicitar sua homenagem floral na Flora Camily.
                        </p>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="disk-faq">
                        <details open>
                            <summary>Como comprar uma coroa de flores?</summary>
                            <p>
                                Clique no botão de WhatsApp desta página. Nossa equipe apresenta as opções
                                disponíveis e orienta sobre personalização e entrega.
                            </p>
                        </details>
                        <details>
                            <summary>É possível colocar uma mensagem na faixa?</summary>
                            <p>
                                Sim. Você pode informar a mensagem desejada durante o atendimento para que
                                ela seja considerada na preparação da homenagem.
                            </p>
                        </details>
                        <details>
                            <summary>Como informar o local da entrega?</summary>
                            <p>
                                Envie pelo WhatsApp os dados do destino, além da data e do horário necessários.
                                A equipe confirma os detalhes do pedido antes da preparação.
                            </p>
                        </details>
                        <details>
                            <summary>Posso consultar as opções antes de comprar?</summary>
                            <p>
                                Sim. Você pode acessar o catálogo ou falar diretamente com a equipe para
                                verificar as homenagens florais disponíveis.
                            </p>
                        </details>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="disk-section pt-0">
        <div class="container">
            <div class="disk-cta">
                <span class="eyebrow text-white">Flora Camily</span>
                <h2>Organize sua homenagem floral pelo WhatsApp.</h2>
                <p>
                    Fale diretamente com nossa equipe para escolher a coroa de flores,
                    personalizar a faixa e combinar os detalhes da entrega.
                </p>
                <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener" class="btn btn-light btn-lg px-4 mt-2">
                    <i class="bi bi-whatsapp me-2"></i>
                    Falar com a Flora Camily
                </a>
            </div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
