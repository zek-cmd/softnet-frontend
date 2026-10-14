// Reading the news, with retries, for the server and for the browser.
//
// Why this file exists at all: the host holding the news sometimes accepts a
// connection and then never answers. The very same request that hangs on one
// try comes back in a quarter of a second on the next, so a single attempt
// cannot tell "the news backend is down" apart from "the backend was slow this
// time".
//
// Two things are done about it:
//
//   1. every request is tried a few times, with a short pause between tries;
//   2. once https has spent its tries, the plain http address of the same file
//      is used instead. It is the same server answering, and measurements on
//      the old hosting showed http answering on every try while https hung on
//      one in six.
//
// A request that fails every one of those tries still fails the way it did
// before: the caller sees the error and reports it. Nothing is ever invented to
// fill a gap.
//
// ON THE SERVER THE PATIENCE IS SHORTER
//
// A page is now rendered on the server when it is asked for (see
// app/pages/news/[slug].vue), so a request that hangs holds a visitor at a blank
// tab — and every reader arriving at that moment waits behind the same news
// backend. So the server tries fewer times and gives up sooner. In the browser,
// where the page is already on screen, the longer patience is kept.

const ATTEMPTS_PER_ADDRESS = 4
const TIMEOUT_MS = 30000

// The server's shorter patience: enough to survive one slow handshake, short
// enough that a backend which is really down cannot hold a page open. Two tries
// at five seconds each, worst case, and the plain http second chance on top of
// that only when the first address was https.
const SERVER_ATTEMPTS = 2
const SERVER_TIMEOUT_MS = 5000

const sleep = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms))

/** The same address over plain http, used only as a second chance. */
function plainHttpUrl(url: string): string {
  return url.startsWith('https://') ? url.replace(/^https:\/\//, 'http://') : ''
}

/**
 * Fetch one news API address, trying again when the host does not answer.
 *
 * The Response is returned as it is, status and all, so a caller can still
 * treat 404 as "no such story" rather than as a failure.
 */
export async function fetchNewsJson(
  url: string,
  options: { attempts?: number; timeout?: number } = {}
): Promise<Response> {
  // "Is this the server?" — during a build, and at request time, there is no
  // window; in the browser there is.
  const onServer = typeof window === 'undefined'

  const attempts = options.attempts ?? (onServer ? SERVER_ATTEMPTS : ATTEMPTS_PER_ADDRESS)
  const timeout = options.timeout ?? (onServer ? SERVER_TIMEOUT_MS : TIMEOUT_MS)

  const addresses = [url]
  const overHttp = plainHttpUrl(url)
  // The plain http address is a second chance only while this runs on the
  // server. A browser on an https page is not allowed to make that request, so
  // there it would only waste a try: in the browser the retries above are what
  // help.
  if (overHttp && onServer) addresses.push(overHttp)

  let lastError: unknown = null

  for (let a = 0; a < addresses.length; a++) {
    const address = addresses[a] as string

    for (let attempt = 1; attempt <= attempts; attempt++) {
      try {
        return await fetch(address, { signal: AbortSignal.timeout(timeout) })
      } catch (error) {
        lastError = error
        console.warn(
          `[news] ${address} did not answer (try ${attempt} of ${attempts}): ` +
            `${(error as Error)?.message ?? error}`
        )
        if (attempt < attempts) await sleep(400 * attempt)
      }
    }

    if (a < addresses.length - 1) {
      console.warn('[news] https is not answering — trying the same file over plain http')
    }
  }

  throw lastError ?? new Error('the news API could not be reached')
}
