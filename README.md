# ymtgatherer

The code behind the "Gatherer for YMtC" at [No Goblins Allowed](http://forum.nogoblinsallowed.com).  Our current implementation currently has nearly six thousand cards in the database from over 30 sets; people are actually building and playing decks using the cards it searches over.  Which is incredibly exciting!

Supported features: 
- [Fairly thorough searching](http://forum.nogoblinsallowed.com/card_search.php)
- [Card-view pages](http://forum.nogoblinsallowed.com/view_card.php?name=Goblin+Rocketeers)
- [Card art](http://forum.nogoblinsallowed.com/view_card.php?name=Beryl%2C+Guardian+of+Flame)
- Autocarding
- Importing from Cockatrice XML files to make it easier to play with the cards you design.

It is built on top of phpbb because for some reason that seemed like a good idea at the time. It does provide a database abstraction layer and uniformity of appearance with the rest of the site, I suppose.

It has a number of fundamental problems:
- It is built on phpbb.
- Maintaining the card list is horrifically manually and almost entirely dependent on the efforts of [one noble soul](http://forum.nogoblinsallowed.com/viewtopic.php?f=15&t=4837).
- Missing features, e.g. no real support for split/flip cards.

Eventually I intend to rebuild the project using a real language and framework.
