<footer class="site-footer">
  <div class="container site-footer__inner">
    <div class="site-footer__brand">
      <strong>Rytkösten sukuseura ry.</strong>
      <span>Kotipaikka: Iisalmi</span>
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

      if ( ! empty( $social_links ) ) :
      ?>
        <ul class="site-footer__social-list" aria-label="Sosiaalisen median linkit">
          <?php foreach ( $social_links as $social_link ) : ?>
            <li class="site-footer__social-item">
              <a class="site-footer__social-link" href="<?php echo esc_url( $social_link['url'] ); ?>">
                <span class="screen-reader-text"><?php echo esc_html( $social_link['label'] ); ?></span>
                <?php if ( 'facebook' === $social_link['icon'] ) : ?>
                  <svg aria-hidden="true" viewBox="0 0 24 24" focusable="false">
                    <path d="M22 12.07C22 6.48 17.52 2 11.93 2 6.35 2 1.86 6.48 1.86 12.07c0 4.82 3.44 8.82 7.94 9.8v-6.92H7.64v-2.88h2.16V9.82c0-2.14 1.27-3.33 3.23-3.33.94 0 1.93.17 1.93.17v2.12h-1.09c-1.07 0-1.4.66-1.4 1.34v1.61h2.38l-.38 2.88h-2v6.92c4.5-.98 7.93-4.98 7.93-9.8Z" />
                  </svg>
                <?php elseif ( 'instagram' === $social_link['icon'] ) : ?>
                  <svg aria-hidden="true" viewBox="0 0 24 24" focusable="false">
                    <path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5Zm0 2a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3H7Zm11.5 1.75a1.25 1.25 0 1 1-2.5 0 1.25 1.25 0 0 1 2.5 0ZM12 8.5A3.5 3.5 0 1 1 8.5 12 3.5 3.5 0 0 1 12 8.5ZM6.5 12a5.5 5.5 0 1 0 11 0 5.5 5.5 0 0 0-11 0Z" />
                  </svg>
                <?php elseif ( 'x' === $social_link['icon'] ) : ?>
                  <svg aria-hidden="true" viewBox="0 0 24 24" focusable="false">
                    <path d="M16.95 3.5H20l-6.11 7.03L21 20.5h-5.05l-3.95-4.79-4.52 4.79H4.39l6.52-6.93L3.5 3.5h5.15l3.57 4.31 4.73-4.31Z" />
                  </svg>
                <?php elseif ( 'youtube' === $social_link['icon'] ) : ?>
                  <svg aria-hidden="true" viewBox="0 0 24 24" focusable="false">
                    <path d="M21.8 7.33a2.52 2.52 0 0 0-1.77-1.8C18.3 5 12 5 12 5s-6.3 0-8.03.53a2.52 2.52 0 0 0-1.77 1.8A26.68 26.68 0 0 0 2 12a26.68 26.68 0 0 0 .2 4.67 2.52 2.52 0 0 0 1.77 1.8C5.7 19 12 19 12 19s6.3 0 8.03-.53a2.52 2.52 0 0 0 1.77-1.8A26.68 26.68 0 0 0 22 12a26.68 26.68 0 0 0-.2-4.67ZM10 15.5v-7l6 3.5-6 3.5Z" />
                  </svg>
                <?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
