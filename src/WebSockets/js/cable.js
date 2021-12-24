/**
 * NOTE:
 *
 * This file should be generated when creating a new project.
 * Its file should be moved to templates.
 *
 */

// Action Cable provides the framework to deal with WebSockets in Rails.
// You can generate new channels where WebSocket features live using the `rails generate channel` command.
//
//= require fly-cube-php-action-cable
//= require_self
//= require_tree ./channels

(function() {
  this.App || (this.App = {});

  App.cable = ActionCable.createConsumer();

}).call(this);
