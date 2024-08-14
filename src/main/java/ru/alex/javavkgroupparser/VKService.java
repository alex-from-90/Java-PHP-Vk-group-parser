package ru.alex.javavkgroupparser;


import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Service;

import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.text.SimpleDateFormat;
import java.util.*;
@Service
public class VKService {

    private static final Logger logger = LoggerFactory.getLogger(VKService.class);

    private final String token;
    private final String version;
    private final ObjectMapper objectMapper;

    public VKService(
            @Value("${vk.api.token}") String token,
            @Value("${vk.api.version}") String version) {
        this.token = token;
        this.version = version;
        this.objectMapper = new ObjectMapper();
    }

    public Map<String, Object> getAllFollowers(String publicName, String dateExample) throws Exception {
        long unixTime = convertDateToUnixTime(dateExample);
        Map<String, Object> groupInfo = getGroupInfo(publicName);
        List<Map<String, String>> followersInfo = new ArrayList<>();
        int offset = 0;
        int maxOffset = calculateMaxOffset(groupInfo);

        while (offset <= maxOffset) {
            Thread.sleep(200);
            JsonNode response = getMembersWithOffset(publicName, offset);
            Thread.sleep(200);
            processResponse(response, unixTime, followersInfo);
            offset += 1000;
        }

        return Map.of("groupInfo", groupInfo, "followers", followersInfo);
    }

    public Map<String, Object> createGroupInfoMap(JsonNode group) {
        return Map.of("id", group.get("id").asInt(), "name", group.get("name").asText(), "members_count", group.get("members_count").asInt());
    }

    private long convertDateToUnixTime(String dateExample) throws Exception {
        return new SimpleDateFormat("MM/dd/yyyy").parse(dateExample).getTime() / 1000;
    }

    private Map<String, Object> getGroupInfo(String publicName) throws Exception {
        logger.info("Fetching group info for publicName: {}", publicName);
        JsonNode response = vkApiRequest("groups.getById", Map.of("group_id", publicName, "fields", "members_count"));
        JsonNode group = response.get("response").get(0);
        validateGroup(group);
        return createGroupInfoMap(group);
    }

    private void validateGroup(JsonNode group) throws Exception {
        if (group == null) {
            throw new Exception("Группа не найдена. Пожалуйста, проверьте правильность названия сообщества.");
        }
    }

    private int calculateMaxOffset(Map<String, Object> groupInfo) throws Exception {
        return Math.min(getMembersCount((Integer) groupInfo.get("id")) / 1000 * 1000, 5000);
    }

    private int getMembersCount(int publicId) throws Exception {
        logger.info("Fetching members count for publicId: {}", publicId);
        JsonNode response = vkApiRequest("groups.getMembers", Map.of("group_id", String.valueOf(publicId), "offset", "0"));
        return response.get("response").get("count").asInt();
    }

    private JsonNode getMembersWithOffset(String publicName, int offset) throws Exception {
        logger.info("Fetching members with offset {} for publicName: {}", offset, publicName);
        Thread.sleep(200);
        return vkApiRequest("groups.getMembers", Map.of(
                "group_id", publicName,
                "sort", "id_desc",
                "offset", String.valueOf(offset),
                "fields", "last_seen,city,bdate,country,has_mobile,university"
        ));
    }

    private void processResponse(JsonNode response, long unixTime, List<Map<String, String>> followersInfo) throws Exception {
        for (JsonNode el : response.get("response").get("items")) {
            Thread.sleep(200);
            if (isUserActive(el, unixTime)) {
                JsonNode userResponse = getUserInfo(el.get("id").asText());
                if (userResponse.has("response")) {
                    Map<String, String> userInfo = createUserInfoMap(el, userResponse);
                    followersInfo.add(userInfo);
                }
            }
        }
    }

    private boolean isUserActive(JsonNode user, long unixTime) {
        return user.has("last_seen") && user.get("last_seen").get("time").asLong() >= unixTime;
    }

    private JsonNode getUserInfo(String userId) throws Exception {
        logger.info("Fetching user info for userId: {}", userId);
        return vkApiRequest("users.get", Map.of(
                "user_ids", userId,
                "fields", "first_name,last_name"
        ));
    }

    private Map<String, String> createUserInfoMap(JsonNode user, JsonNode userResponse) {
        Map<String, String> userInfo = new HashMap<>();
        userInfo.put("id", user.get("id").asText());
        userInfo.put("Имя", userResponse.get("response").get(0).get("first_name").asText("Unknown"));
        userInfo.put("Фамилия", userResponse.get("response").get(0).get("last_name").asText("Unknown"));
        userInfo.put("День рождения", user.has("bdate") ? user.get("bdate").asText() : "");
        userInfo.put("Город", user.has("city") ? user.get("city").get("title").asText() : "");
        userInfo.put("Страна", user.has("country") ? user.get("country").get("title").asText() : "");
        userInfo.put("Мобильный телефон", user.has("has_mobile") ? user.get("has_mobile").asText() : "");
        userInfo.put("Университет", user.has("university") ? user.get("university").get("name").asText() : "");
        userInfo.put("Ссылка", "https://vk.com/id" + user.get("id").asText());
        return userInfo;
    }

    private JsonNode vkApiRequest(String method, Map<String, String> params) throws Exception {
        StringBuilder urlBuilder = new StringBuilder("https://api.vk.com/method/").append(method).append("?");
        params.forEach((key, value) -> urlBuilder.append(key).append("=").append(value).append("&"));
        urlBuilder.append("access_token=").append(token).append("&v=").append(version);

        String requestUrl = urlBuilder.toString();
        logger.info("Sending request to VK API: {}", requestUrl);

        URI uri = new URI(requestUrl);
        URL url = uri.toURL();
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setRequestMethod("GET");

        JsonNode response = objectMapper.readTree(conn.getInputStream());

        if (response.has("error")) {
            logger.error("VK API Error: {}", response.get("error").get("error_msg").asText());
            throw new Exception("VK API Error: " + response.get("error").get("error_msg").asText());
        }

        logger.info("Received response from VK API: {}", response.toString());

        return response;
    }
}