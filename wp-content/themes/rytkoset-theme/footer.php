<?php
/**
 * Footer: pre-footer newsletter band + slim footer.
 *
 * The pre-footer is rendered here for every page (large on the front page,
 * compact elsewhere) so it cannot be forgotten on an individual template.
 * Each partial hides itself when there is no newsletter form to show.
 */

if ( is_front_page() ) {
	get_template_part( 'template-parts/pre-footer', 'large' );
} else {
	get_template_part( 'template-parts/pre-footer', 'compact' );
}
?>

<footer class="site-footer" role="contentinfo">
  <div class="container">
    <div class="site-footer__inner">
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
        <a class="site-footer__contact" href="mailto:<?php echo esc_attr( $contact_email ); ?>">
          <svg class="site-footer__contact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <rect x="3" y="5" width="18" height="14" rx="2"></rect>
            <path d="m3 7 9 6 9-6"></path>
          </svg>
          <span><?php echo esc_html( $contact_email ); ?></span>
        </a>

        <?php
        $social_links = rytkoset_theme_get_social_links();

        if ( ! empty( $social_links ) ) :
        ?>
          <ul class="site-footer__social-list" aria-label="Sosiaalisen median linkit">
            <?php foreach ( $social_links as $social_link ) : ?>
              <?php
              if ( empty( $social_link['icon_file'] ) ) {
                continue;
              }
              ?>
                <li class="site-footer__social-item">
                  <a class="site-footer__social-link" href="<?php echo esc_url( $social_link['url'] ); ?>">
                    <span class="screen-reader-text"><?php echo esc_html( $social_link['label'] ); ?></span>
                    <?php echo rytkoset_theme_inline_icon( $social_link['icon_file'], 'social' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                  </a>
                </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <div class="site-footer__legal">
      <span>&copy; Rytkösten sukuseura ry. &middot; Kotipaikka: Iisalmi</span>
    </div>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
