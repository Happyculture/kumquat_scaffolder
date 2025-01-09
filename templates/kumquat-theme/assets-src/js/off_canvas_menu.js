(function (Drupal, debounce, once, document, window) {
  /**
   * Function called when the window is resized.
   *
   * Show or hide the off screen canvas according to the button state when it
   * becomes hidden in CSS.
   */
  const onWindowResize = () => {
    document.querySelectorAll('.off-canvas-menu__button:not(.off-canvas-menu__button--close)')
      .forEach((buttonEl) => {
        const target = document.getElementById(buttonEl.getAttribute('aria-controls'));

        // If the control button is hidden, show the target.
        if (buttonEl.offsetParent === null) {
          target.removeAttribute('aria-hidden');
          buttonEl.parentNode.querySelectorAll('.off-canvas-menu__button')
            .forEach((el) => {
              el.setAttribute('aria-expanded', 'true');
            });
        } else if (!buttonEl.getAttribute('data-off-canvas-open-by-click')) {
          // If the button is visible and the canvas has not been opened by a
          // previous click, hide the target.
          target.setAttribute('aria-hidden', 'true');
          buttonEl.parentNode.querySelectorAll('.off-canvas-menu__button')
            .forEach((el) => {
              el.setAttribute('aria-expanded', 'false');
            });
        }
      });
  };

  /**
   * Function called when an off canvas trigger button is clicked.
   *
   * Show or hide the off screen canvas according to the button state.
   *
   * @param event
   */
  const onButtonClick = (event) => {
    const currentTargetEl = event.currentTarget;
    const target = document.getElementById(currentTargetEl.getAttribute('aria-controls'));

    if (currentTargetEl.getAttribute('aria-expanded') === 'true') {
      target.setAttribute('aria-hidden', 'true');
      let parentEl = target;
      while (parentEl.closest('header') && parentEl !== parentEl.closest('header')) {
        parentEl = parentEl.closest('header');
        parentEl.querySelectorAll('.off-canvas-menu__button')
          .forEach((buttonEl) => {
            buttonEl.setAttribute('aria-expanded', 'false');
            buttonEl.removeAttribute('data-off-canvas-open-by-click');
          });
        parentEl.classList.remove('with-menu');
      }
      document.querySelector('body')
        .classList.remove('menu-displayed');
    } else {
      target.removeAttribute('aria-hidden');
      let parentEl = target;
      while (parentEl.closest('header') && parentEl !== parentEl.closest('header')) {
        parentEl = parentEl.closest('header');
        parentEl.querySelectorAll('.off-canvas-menu__button')
          .forEach((buttonEl) => {
            buttonEl.setAttribute('aria-expanded', 'true');
            buttonEl.setAttribute('data-off-canvas-open-by-click', 'true');
          });
        parentEl.classList.add('with-menu');
      }
      document.querySelector('body')
        .classList.add('menu-displayed');
    }
  };

  Drupal.behaviors.OffCanvasMenu = {
    attach: function () {
      once('OffCanvasMenu', '.off-canvas-menu__button').forEach((el) => {
        el.addEventListener('click', onButtonClick);
      });

      once('OffCanvasMenu', document.body).forEach(() => {
        window.addEventListener('resize', debounce(onWindowResize, 50));
        onWindowResize();
      });
    }
  };
})(Drupal, Drupal.debounce, once, document, window);
