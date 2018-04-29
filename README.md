# PHPVisor
PHPVisor : Process managment system in client-server architect for unix sytems

# Motivation
PHPVisor is about the main idea of SuperVisor (http://supervisord.org/), but implemented in PHP, and serve a  bit less functionality than the SuperVisor.
If you want to know more about SuperVisor, check it's github: https://github.com/Supervisor/supervisor

# External Code
I used for my project, a light-weight sockat library, written in PHP.
This a minimal lib, to serve a minimal OOP layer over PHP socket API functions.
The PHPVisor based on a light-weight OOP library written in PHP by clue (https://github.com/clue/).
The PHPVisor's External directory, contains clue's php-socket-raw project (https://github.com/clue/php-socket-raw).
Thanks for him, to made this OOP wrapper, it makes the concept much cleaner, and object oriented.
 

# Future plans
Remove clue's code fom repository, and make the whole project composer compatible.
Then I can use the lib as a dependency.

# License
[MIT](LICENSE)