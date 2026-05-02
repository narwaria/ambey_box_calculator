(function (Drupal, once) {
  Drupal.behaviors.ambeyBoxCalculator = {
    attach(context) {
      once('ambey-box-calculator', '.ambey-calculator', context).forEach(() => {});
    }
  };
})(Drupal, once);
