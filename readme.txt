=== One Click Login ===
Contributors: bestpluginswordpress, freemius
Tags: authentication,google,google login,login,oauth,on click login,register,registration,signin,one click login,sign in,social login,single-sign-on,sso,google apps,gsuite,g suite
Requires at least: 4.0
Tested up to: 6.0
Requires PHP: 5.8
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Users can login and register with Gmail and G-Suite. Supports whitelisting domains and individual email addresses. Supports disabling password usage.

== Description ==
[youtube http://www.youtube.com/watch?v=blnUvT9ZGRk]

Allows users to login and register with Gmail and G-Suite.

You can add an unlimited amount of groups and all groups can be customized separately. Groups consist of two different types: domains and whitelisting.

* Domains type accepts email addresses by their domain, for example, @gmail.com or @mydomain.com can be used as a domain. Everyone with a @gmail.com or @mydomain.com account would be able to use this plugin.

* Whitelisting type only accepts predefined email addresses such as john@gmail.com or jane@mydomain.com.

= Registering =
If enabled, users of a group can register new accounts by logging in with One Click Login.

By default, these users are given the "Subscriber" role but you can also customize it up to the "Administrator" role.

= Prevent password usage =
If enabled, users of a group can be prevented from using their password in any way.

These users can’t login with their password, change their password, or reset their password.

They can only login with One Click Login.

This is particularly handy for companies as these users lose their ability to log in once their G-Suite account has been disabled.

= Password regeneration =
If enabled, users of a group will have their password regenerated automatically whenever they log in.

This can only be enabled if the group has "Prevent password usage" enabled.

= Ignored users =
Sometimes a domain (such as @gmail.com or @mydomain.com) contains a huge amount of users and you want to prevent password usage for most of them but not all. This is where Ignored users come in handy.

With Ignored users, you can take a group and select which users can use their password normally while preventing it for the rest of the users in the group.

= Hiding the plugin =

One Click Login can also be hidden from the admin page and from the login page.

The menu and One Click Login button will only be visible if the url contains a specific word (https://www.mydomain.com/wp-admin/?show_hidden_page=1). The specific word ("show_hidden_page") can be set in the plugin settings.

This is great when you want to hide the admin panel and the login button from regular users and only show them to your friends and colleagues.

= Exporting / Importing =
One Click Login supports exporting the settings for easy importing.

There are two ways to import settings:
* Paste them to the "Import settings" textarea and click "Save Changes".
* Set them to ONE_CLICK_LOGIN_IMPORT_ON_PLUGIN_ACTIVATION environment variable on your system. Importing from the environment variable only occurs once, during the initial activation of this plugin.

== Installation ==
You will need a Google OAuth Client ID & Secret, which is easy and quick to generate using this tutorial: https://youtu.be/TvVdqVNpcwQ

Importing instructions can be found from the "Import / Export" tab inside the plugin settings.

== Screenshots ==
1. Allows anyone from @gmail.com and @mydomain.com to login and register. New users will gain "Subscriber" / "Administrator" role automatically. Administrators can not use their password in any way, meaning they must login with One Click Login plugin.
2. Allows anyone from john@mydomain.com and jane@gmail.com to login and register. They will gain "Administrator" role automatically. They can not use their password in any way, meaning they must login with One Click Login plugin.
3. Importing / Exporting.