import { match } from "ts-pattern";
import { z } from "zod";

const _emptyStringToUndefined = z.literal("").transform(() => undefined);

export const asOptional = <T>(schema: z.ZodType<T>) =>
  schema.optional().or(_emptyStringToUndefined);

export const zodErrorMap: z.ZodErrorMap = (issue) => {
  const REQUIRED_ERROR_MESSAGE: string = "This field is required.";

  let message = match(issue)
    .with({ code: "invalid_value" }, (issue) => {
      if (!issue.values?.length) {
        return REQUIRED_ERROR_MESSAGE;
      }
    })
    .with({ code: "invalid_format" }, (issue) => {
      if (issue.format === "email") {
        return "Please input a valid email address.";
      }

      if (issue.format === "url") {
        return "Please input a valid URL.";
      }
    })
    .with({ code: "invalid_type" }, (issue) => {
      if (issue.received === "nan" || issue.received === "null" || issue.received === "undefined") {
        return REQUIRED_ERROR_MESSAGE;
      }
    })
    .with({ code: "too_small" }, () => {
      return REQUIRED_ERROR_MESSAGE;
    })
    .otherwise(() => {
      return issue.message;
    });

  message = message || issue.message || "Invalid value";
  message = message.endsWith(".") ? message : `${message}.`;

  return { message };
};

export { z };
