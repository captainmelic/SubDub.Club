All believed-to-be necessary files taken from the droplet. This repository may not be able to create an exact replica of the original droplet.

## Inspiration
The pub subs on the first day of the hackathon really struck a chord with us. They were so succulent and delicious that we couldn't stop talking about them.
## What it does
Tracks the past 7 days of user-submitted subs and their ratings, as well as their streak (how many days in a row they submitted a sub). The user submits an image of their sub for identification with MobileNetV2 in order to make sure they're not trying to cheat the leaderboard.
## How we built it
Vibe coded with Claude Sonnet 5.5 and Copilot for debugging. It runs on a LAMP Stack hosted on DigitalOcean, entirely on a single vCPU, 1 GB of RAM, and 25 GB of disk space.
## Challenges we ran into
We initially planned on using MediaPipe; however, this required 2 gigabytes of RAM when we only had 1. We had to keep our budget low, especially with RAM prices these days, so we opted for a more efficient model.
## Accomplishments that we're proud of
Getting a full stack up and running in a single day was a great achievement; previously, having to do this was always a chore. Also, getting MobileNetV2- something we've never used before- to identify a sub (and what's not a sub) was very satisfying.
## What we learned
We learned how to pivot quickly from out-of-scope ideas to something manageable in the time frame. We also learned that better ideas should probably be thought of long in advance, as well as having multiple, so that this out-of-scope issue can be mitigated. Other than that, we learned some more use cases for MediaPipe and MobileNetV2.
## What's next for SubDub Club
A daily sub wordle with all words using the prefix "sub-" to keep all the fans entertained and coming back for more.
