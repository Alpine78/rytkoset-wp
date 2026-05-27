<footer class="site-footer" role="contentinfo">
  <div class="container site-footer__inner">
    <div class="site-footer__brand">
      <?php
      rytkoset_theme_the_logo(
        array(
          'class'      => 'site-logo site-logo--footer',
          'link_class' => 'site-logo__link site-logo__link--footer',
        )
      );
      ?>
      <div class="site-footer__brand-text">
        <strong>Rytkösten sukuseura ry.</strong>
        <span>Kotipaikka: Iisalmi</span>
      </div>
    </div>

    <nav class="site-footer__nav" aria-label="Alavalikko">
      <?php
      wp_nav_menu(
        array(
          'theme_location' => 'footer',
          'container'      => false,
          'menu_class'     => 'site-footer__menu',
          'fallback_cb'    => false,
          'depth'          => 1,
        )
      );
      ?>
    </nav>

    <div class="site-footer__meta">
      <?php $contact_email = rytkoset_theme_get_contact_email(); ?>
      <span>Yhteydenotot: <a href="mailto:<?php echo esc_attr( $contact_email ); ?>"><?php echo esc_html( $contact_email ); ?></a></span>

      <?php
      $social_links = rytkoset_theme_get_social_links();

      if ( ! empty( $social_links ) ) :
      ?>
        <ul class="site-footer__social-list" aria-label="Sosiaalisen median linkit">
          <?php foreach ( $social_links as $social_link ) : ?>
            <?php
            if ( empty( $social_link['icon_src'] ) ) {
              continue;
            }
            ?>
              <li class="site-footer__social-item">
                <a class="site-footer__social-link" href="<?php echo esc_url( $social_link['url'] ); ?>">
                  <span class="screen-reader-text"><?php echo esc_html( $social_link['label'] ); ?></span>
                  <img src="<?php echo esc_url( $social_link['icon_src'] ); ?>" alt="" aria-hidden="true" />
                </a>
              </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <?php
    $newsletter_markup = function_exists( 'rytkoset_theme_get_footer_newsletter_markup' )
      ? rytkoset_theme_get_footer_newsletter_markup()
      : '';

    if ( '' !== $newsletter_markup ) {
      echo $newsletter_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built by the theme and AcyMailing form renderer.
    }
    ?>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
