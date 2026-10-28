/**
 * buildQueryString — serialize request params without the `undefined` / `null`
 * coercion of `URLSearchParams`.
 *
 * `new URLSearchParams({ search: undefined })` produces `search=undefined`, so
 * a callsite that expressed "omit this filter" by sending `undefined` had the
 * server filter by the literal word and returned nothing (#408). Omission is
 * now the client's job: only `undefined` and `null` are dropped, while `''`,
 * `0` and `false` stay because they are legitimate filter values.
 */
export function buildQueryString(params = {}) {
  const search = new URLSearchParams()

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null) continue

    if (Array.isArray(value)) {
      for (const item of value) {
        if (item === undefined || item === null) continue
        search.append(key, item)
      }
      continue
    }

    search.append(key, value)
  }

  return search.toString()
}

export default buildQueryString
