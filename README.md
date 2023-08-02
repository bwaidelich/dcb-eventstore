# Dynamic Consistency Boundary Event Store

Implementation of the Dynamic Consistency Boundary pattern [described by Sara Pellegrini](https://sara.event-thinking.io/2023/04/kill-aggregate-chapter-1-I-am-here-to-kill-the-aggregate.html)

The main purpose of this package is to explore the idea, find potential pitfalls and to spread the word.
It merely provides interfaces and core models to implement the DCB pattern.
To see the pattern "in action", make sure to have a look at the [wwwision/dcb-example](https://github.com/bwaidelich/dcb-example) package.
To actually commit events, a corresponding adapter package is required, for example [wwwision/dcb-eventstore-doctrine](https://github.com/bwaidelich/dcb-eventstore-doctrine).

## Usage

Install via [composer](https://getcomposer.org):

```shell
composer require wwwision/dcb-eventstore
```

## Specification

See [Specification.md](Specification.md) (work in progress)

## Contribution

Contributions in the form of [issues](https://github.com/bwaidelich/dcb-eventstore/issues), [pull requests](https://github.com/bwaidelich/dcb-eventstore/pulls) or [discussions](https://github.com/bwaidelich/dcb-eventstore/discussions) are highly appreciated

## License

See [LICENSE](./LICENSE)

CI Test
