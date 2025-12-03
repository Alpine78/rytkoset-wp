<footer class="site-footer">
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
      <span>Yhteydenotot: <a href="mailto:info@rytkoset.net">info@rytkoset.net</a></span>

      <?php
      $social_links = rytkoset_theme_get_social_links();
      $icon_files   = array(
        'facebook'  => 'Facebook.svg',
        'youtube'   => 'YouTube.svg',
        'instagram' => 'Instagram.svg',
        'x'         => 'X.svg',
      );

      if ( ! empty( $social_links ) ) :
      ?>
        <ul class="site-footer__social-list" aria-label="Sosiaalisen median linkit">
          <?php foreach ( $social_links as $social_link ) : ?>
            <?php
            $icon = $social_link['icon'];
            if ( isset( $icon_files[ $icon ] ) ) :
              $icon_src = get_template_directory_uri() . '/assets/icons/social/' . $icon_files[ $icon ];
            ?>
              <li class="site-footer__social-item">
                <a class="site-footer__social-link" href="<?php echo esc_url( $social_link['url'] ); ?>">
                  <span class="screen-reader-text"><?php echo esc_html( $social_link['label'] ); ?></span>
                  <img src="<?php echo esc_url( $icon_src ); ?>" alt="" aria-hidden="true" />
                </a>
              </li>
            <?php endif; ?>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
