<?php
/*
Plugin Name: GiantBomb Widget
Plugin URI: http://vincecima.com/blog/giantbomb-wordpress-widget/
Description: Display your activity on <a href="http://giantbomb.com">GiantBomb</a> or your GamerGrade card in a customizable widget.
Version: 1.2
Author: Vince Cima
Author URI: http://vincecima.com
*/
/* Copyright (c) 2009 Vince Cima. All rights reserved.
//
// Released under the GPL license
// http://www.opensource.org/licenses/gpl-license.php
//
// This is an add-on for WordPress
// http://wordpress.org/
//
// Thanks to GiantBomb.com, its users and Whiskey Media for putting together a great source of information and API.
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of 
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
*************************************************************************
*/

include_once(ABSPATH . WPINC . '/rss.php');
include_once("phpQuery.php");

class GiantBombWidget extends WP_Widget {
	const gamerGradeLinkMarkup = "<a href='http://www.giantbomb.com/profile/%1\$s/games/'><img src='http://gamergrade.giantbomb.com/%1\$s.png' /></a>";
	const activityFeedUrl = "http://www.giantbomb.com/feeds/activity/%s/";
	
    /** constructor */
    function GiantBombWidget() {
        parent::WP_Widget(false, $name = 'GiantBomb Widget');	
    }

    /** @see WP_Widget::widget */
    function widget($args, $instance) {		
        extract( $args );

		echo $before_widget;

		$title = apply_filters('widget_title', $instance['gbw-title']);
		echo $before_title . $title . $after_title;

		if($instance["gbw-showGamerCard"] == true) {
			printf(self::gamerGradeLinkMarkup, strtolower($instance['gbw-username']));
		}
		
		if($instance["gbw-showActivityFeed"] == true) {
			$rss = fetch_rss(sprintf(self::activityFeedUrl, $instance['gbw-username']));
			echo(self::parseActivityEntries($rss, $instance['gbw-max-items']));
		}
		
		echo $after_widget;
    }

    /** @see WP_Widget::form */
    function form($instance) {
	
		echo '<p>';		
		printf('<label for="%s">', $this->get_field_id('gbw-title')); 
		_e('Title:');
		echo '</label>';
		printf('<input class="widefat" id="%s" name="%s" type="text" value="%s">', $this->get_field_id('gbw-title'),  $this->get_field_name('gbw-title'), $instance['gbw-title']);
		echo '</p>';
		
		echo '<p>';		
		printf('<label for="%s">', $this->get_field_id('gbw-username')); 
		_e('GiantBomb username:');
		echo '</label>';
		printf('<input class="widefat" id="%s" name="%s" type="text" value="%s">', $this->get_field_id('gbw-username'),  $this->get_field_name('gbw-username'), $instance['gbw-username']);
		echo '</p>';
		
		echo '<p>';
		if($instance["gbw-showGamerCard"] == true) {
			$checkboxChecked = "checked";
		}
		printf('<input id="%s" name="%s" type="checkbox" value="%s" class="checkbox" %s>', $this->get_field_id('gbw-showGamerCard'),  $this->get_field_name('gbw-showGamerCard'), "true", $checkboxChecked);		
		printf('<label for="%s">', $this->get_field_id('gbw-showGamerCard')); 
		_e(' Show GamerCard');
		echo '</label>';
		echo '<br>';
		
		if($instance["gbw-showActivityFeed"] == true) {
			$checkboxChecked = "checked";
		}
		else {
			$checkboxChecked = "";
		}
		printf('<input id="%s" name="%s" type="checkbox" value="%s" class="checkbox" %s>', $this->get_field_id('gbw-showActivityFeed'),  $this->get_field_name('gbw-showActivityFeed'), "true", $checkboxChecked);		
		printf('<label for="%s">', $this->get_field_id('gbw-showActivityFeed')); 
		_e(' Show Activity Feed');
		echo '</label>';
		echo '<br>';
		
		printf('<label for="%s">', $this->get_field_id('gbw-max-items'));
		_e('Number of entries to show:');
		echo '</label>';
		printf('<input id="%s" name="%s" type="text" size="2" value="%s"', $this->get_field_id("gbw-max-items"), $this->get_field_name('gbw-max-items'), $instance['gbw-max-items']);
		echo '<br /><small>(at most 15)</small>';
		
		echo '</p>';
    }

    /** @see WP_Widget::update */
    function update($new_instance, $old_instance) {
		$instance = $old_instance;
		
		$instance['gbw-title'] = strip_tags(stripslashes(trim($new_instance['gbw-title'])));
		$instance['gbw-username'] = strip_tags(stripslashes(trim($new_instance['gbw-username'])));
		
		$maxItems = strip_tags(stripslashes($new_instance['gbw-max-items']));
		if(is_numeric($maxItems)) {
			$instance['gbw-max-items'] = max(1, min($maxItems, 15));
		}
		else {
			$instance['gbw-max-items'] = 5;
		}
		
		$instance['gbw-showGamerCard'] = strip_tags(stripslashes($new_instance['gbw-showGamerCard']));
		$instance['gbw-showActivityFeed'] = strip_tags(stripslashes($new_instance['gbw-showActivityFeed']));
		
        return $instance;
    }

	private function parseActivityEntries($rss, $maxItems) {
		//The parsed activity entries as a html list.
		$activityListMarkup = null;
		//Individual entry markup as a html list item.
		$activityMarkup = null;
		//Class name for each element
		$activityClassName = null;
		//Split our rss into individual records
		$items = array_slice($rss->items, 0, $maxItems);
		foreach($items as $item) {
			//Info we care about is only contained in the description element
			$item = $item['description'];
			
			phpQuery::newDocument($item);
			
			//Transform all GiantBomb links from relative to absolute
			foreach(pq("[href]") as $linkNode) {
				self::transformRelativeLinks($linkNode);
			}
			
			//Most updates contain a strong element that is used as the headline.
			if(pq("strong")->length > 0) {
				$activityMarkup = pq("strong:first")->html();
				$activityClassName = "gbw-activity-update";
			}
			
			//Status updates use a span instead of the strong.
			else if(pq("span")->length > 0) {
				$activityMarkup = pq("span:first")->html();
				$activityClassName = "gbw-status-update";
			}
			
			$activityListMarkup = $activityListMarkup . "<li class='" . $activityClassName . "'>" . $activityMarkup . "</li>";
		}
		$activityListMarkup = "<ul>" . $activityListMarkup . "</ul>";
		return $activityListMarkup;
	}
	
	private function transformRelativeLinks($linkNode) {
		$linkNode = pq($linkNode);
		if(substr($linkNode->attr("href"), 0, 1) == "/") {
			$linkNode->attr("href", "http://www.giantbomb.com" . $linkNode->attr("href"));
		}
		
	}
} // class GiantBombActivityWidget

add_action('widgets_init', create_function('', 'return register_widget("GiantBombWidget");'));

?>