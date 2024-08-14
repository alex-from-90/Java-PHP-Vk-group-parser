package ru.alex.javavkgroupparser;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestParam;

import java.util.Map;

@Controller
public class VKController {

    @Autowired
    private VKService vkService;

    @GetMapping("/")
    public String index() {
        return "index";
    }

    @PostMapping("/get-subscribers")
    public String getSubscribers(@RequestParam("public") String publicName,
                                 @RequestParam("date_example") String dateExample,
                                 Model model) {
        try {
            var result = vkService.getAllFollowers(publicName, dateExample);
            Object groupInfoObj = result.get("groupInfo");

            // Проверяем, что groupInfoObj является Map<String, Object>
            if (groupInfoObj instanceof Map<?, ?> tempMap) {
                // Проверяем, что ключи являются строками
                boolean validMap = true;

                for (Map.Entry<?, ?> entry : tempMap.entrySet()) {
                    if (!(entry.getKey() instanceof String) || entry.getValue() == null) {
                        validMap = false;
                        break;
                    }
                }

                if (validMap) {
                    @SuppressWarnings("unchecked")
                    Map<String, Object> groupInfo = (Map<String, Object>) tempMap;
                    model.addAttribute("id", groupInfo.get("id"));
                    model.addAttribute("name", groupInfo.get("name"));
                    model.addAttribute("members_count", groupInfo.get("members_count"));
                } else {
                    // Если структура данных неверна
                    model.addAttribute("error", "Неверный формат данных для groupInfo.");
                }
            } else {
                // Если groupInfoObj не является Map
                model.addAttribute("error", "Неверный формат данных для groupInfo.");
            }

            model.addAttribute("followers", result.get("followers"));
        } catch (Exception e) {
            model.addAttribute("error", "Ошибка: " + e.getMessage());
        }
        return "result";
    }

}