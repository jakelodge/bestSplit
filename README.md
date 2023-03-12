# bestSplit

Find the fastest specified distance within a (Strava) activity / JSON activity stream.
Example use:
```
php bestSplit.php -f https://www.strava.com/activities/1300063440 -w 5000
```
>fastest:
>10m -- 5010m
>00:00:08 -- 00:19:33
>
>splits:
>1  00:03:49
>2  00:03:51
>3  00:03:44
>4  00:04:05
>5  00:04:02
>
>duration:
>00:19:25

```
php bestSplit.php -f 1300063440.json
```
(Where file contains stream data)
>fastest:
>10m -- 1010m
>00:00:08 -- 00:03:51
>
>duration:
>00:03:43

* NB: Will only work on non-private activities (if by URL), also think that flybys must be [enabled](https://support.strava.com/hc/en-us/articles/360015478252-Flybys-Privacy-Controls).

## Run tests
Having issued `composer install`:
```./vendor/bin/psalm```