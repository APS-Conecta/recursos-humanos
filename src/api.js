import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * The pages read «Recursos Humanos»' own OCS API v1 (ApiController). @nextcloud/axios sends the
 * session's request token, and the payload sits inside the OCS envelope.
 */
export async function ocsGet(path, params = {}) {
	const { data } = await axios.get(generateOcsUrl(`/apps/recursos_humanos/api/v1/${path}`), { params })
	return data.ocs.data
}

export async function ocsPost(path, body = {}) {
	const { data } = await axios.post(generateOcsUrl(`/apps/recursos_humanos/api/v1/${path}`), body)
	return data.ocs.data
}

/** The Spanish reason a refused call answers with (aps-common's `{message}`), or null. */
export function refusal(error) {
	return error?.response?.data?.ocs?.data?.message ?? null
}
